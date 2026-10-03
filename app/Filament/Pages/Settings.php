<?php

namespace App\Filament\Pages;

use App\Filament\Support\Uploads;
use App\Models\AuditLog;
use App\Models\KnowledgeChunk;
use App\Models\Setting;
use App\Services\AssistantService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Settings';

    /** Keys managed on this page. */
    public const KEYS = ['potraz_licence', 'hero_video_path', 'sms_sender_id'];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => Setting::query()->where('key', $k)->value('value')])->all());
    }

    public function form(Schema $schema): Schema
    {
        $assistantOn = app(AssistantService::class)->enabled();
        $chunks = KnowledgeChunk::count();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Licensing')->schema([
                    TextInput::make('potraz_licence')
                        ->label('POTRAZ licence')
                        ->maxLength(120)
                        ->placeholder('application in progress')
                        ->helperText('Shown in the site footer. Leave empty to show "application in progress".'),
                ]),
                Section::make('Homepage')->schema([
                    Uploads::public('hero_video_path', 'video')
                        ->label('Hero background video')
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->maxSize(4096)
                        ->helperText('MP4 or WebM, up to 4 MB, no sound. Loaded only on fast connections; the still image is used otherwise.'),
                ]),
                Section::make('Messaging')->schema([
                    TextInput::make('sms_sender_id')
                        ->label('SMS sender ID')
                        ->maxLength(11)
                        ->regex('/^[A-Za-z0-9 ]{1,11}$/')
                        ->placeholder((string) config('fspra.sms.sender_id'))
                        ->helperText('Up to 11 letters or digits, as approved by the SMS provider. Leave empty to use the configured default ('.config('fspra.sms.sender_id').').'),
                ]),
                Section::make('Assistant')->schema([
                    Text::make($assistantOn
                        ? 'The assistant is enabled: a Gemini API key is configured and today\'s token cap has not been reached.'
                        : 'The assistant is answering from the knowledge base only: no Gemini API key is configured or today\'s token cap has been reached. Set GEMINI_API_KEY in the server environment to enable it.'),
                    Text::make("Knowledge base: {$chunks} chunks from published FAQs, services, notices and pages. It is rebuilt automatically when those are saved here."),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $changed = [];

        foreach (self::KEYS as $key) {
            $value = $data[$key] ?? null;
            $value = is_array($value) ? (array_values($value)[0] ?? null) : $value;
            $value = filled($value) ? (string) $value : null;
            if (Setting::query()->where('key', $key)->value('value') !== $value) {
                Setting::put($key, $value);
                $changed[] = $key;
            }
        }

        if ($changed) {
            AuditLog::record('admin.settings.updated', null, ['keys' => $changed]);
        }

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rebuildKnowledge')
                ->label('Rebuild assistant knowledge')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Re-indexes published FAQs, enabled services, recent notices and published pages for the assistant.')
                ->action(function () {
                    $n = app(AssistantService::class)->rebuildKnowledge();
                    AuditLog::record('admin.assistant.knowledge_rebuilt', null, ['chunks' => $n]);
                    Notification::make()->title('Knowledge rebuilt')->body("{$n} chunks indexed.")->success()->send();
                }),
        ];
    }
}
