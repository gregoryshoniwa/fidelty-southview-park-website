<?php

namespace App\Filament\Resources\Threads\Pages;

use App\Filament\Resources\Notices\NoticeResource;
use App\Filament\Resources\Threads\ThreadResource;
use App\Models\AuditLog;
use App\Models\Notice;
use App\Models\Thread;
use App\Models\User;
use App\Services\MessagingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ViewThread extends ViewRecord
{
    protected static string $resource = ThreadResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->reference.': '.$this->getRecord()->subject;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Mark resident messages as read by staff when the committee opens the thread.
        $this->getRecord()->messages()->where('sender_type', 'resident')->whereNull('read_by_staff_at')->update(['read_by_staff_at' => now()]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Thread $thread */
        $thread = $this->getRecord();

        return [
            Action::make('reply')
                ->label('Reply')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalHeading('Reply to resident')
                ->modalDescription('Your reply is sent as "The committee" and the resident is notified.')
                ->schema([
                    Textarea::make('body')->label('Message')->required()->rows(8)->maxLength(5000),
                ])
                ->action(function (array $data) use ($thread) {
                    app(MessagingService::class)->post($thread, 'committee', auth()->id(), $data['body']);
                    AuditLog::record('admin.thread.replied', $thread);
                    Notification::make()->title('Reply sent')->success()->send();
                    $this->refreshThread();
                }),
            Action::make('assign')
                ->label('Assign to')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->fillForm(fn () => ['assigned_to' => $thread->assigned_to])
                ->schema([
                    Select::make('assigned_to')
                        ->label('Committee member')
                        ->options(fn () => User::role(User::COMMITTEE_ROLES)->where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('Unassigned'),
                ])
                ->action(function (array $data) use ($thread) {
                    $thread->update(['assigned_to' => $data['assigned_to'] ?: null]);
                    AuditLog::record('admin.thread.assigned', $thread, ['assigned_to' => $data['assigned_to'] ?: null]);
                    Notification::make()->title('Thread assigned')->success()->send();
                    $this->refreshThread();
                }),
            ActionGroup::make([
                Action::make('close')
                    ->label('Close thread')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn () => $thread->status !== 'closed')
                    ->requiresConfirmation()
                    ->modalDescription('The resident can still reply, which reopens the thread.')
                    ->action(function () use ($thread) {
                        $thread->update(['status' => 'closed']);
                        AuditLog::record('admin.thread.closed', $thread);
                        Notification::make()->title('Thread closed')->success()->send();
                        $this->refreshThread();
                    }),
                Action::make('reopen')
                    ->label('Reopen thread')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn () => $thread->status === 'closed')
                    ->action(function () use ($thread) {
                        $thread->update(['status' => 'open']);
                        AuditLog::record('admin.thread.reopened', $thread);
                        $this->refreshThread();
                    }),
                Action::make('promote')
                    ->label('Promote to notice')
                    ->icon(Heroicon::OutlinedMegaphone)
                    ->requiresConfirmation()
                    ->modalDescription('Creates an unpublished draft notice from this thread. Remove any personal details before publishing.')
                    ->action(function () use ($thread) {
                        $last = $thread->messages()->where('sender_type', 'resident')->reorder()->latest('id')->first();
                        $body = $last ? '<p>'.nl2br(e($last->body)).'</p>' : '<p></p>';
                        $base = Str::slug($thread->subject) ?: 'notice';
                        $slug = $base;
                        for ($i = 2; Notice::where('slug', $slug)->exists(); $i++) {
                            $slug = $base.'-'.$i;
                        }
                        $notice = Notice::create([
                            'title' => Str::limit($thread->subject, 250, ''),
                            'slug' => $slug,
                            'body' => $body,
                            'category' => 'services',
                            'author_id' => auth()->id(),
                            'published_at' => null,
                            'send_sms' => false,
                            'pinned' => false,
                        ]);
                        AuditLog::record('admin.thread.promoted_to_notice', $thread, ['notice_id' => $notice->id]);
                        Notification::make()->title('Draft notice created')->body('Edit and publish it when ready.')->success()->send();
                        $this->redirect(NoticeResource::getUrl('edit', ['record' => $notice]));
                    }),
            ])->label('More')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
        ];
    }

    protected function refreshThread(): void
    {
        $this->getRecord()->refresh();
        $this->getRecord()->unsetRelation('messages');
    }
}
