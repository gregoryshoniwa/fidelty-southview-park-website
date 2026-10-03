<?php

namespace Database\Seeders;

use App\Models\CommunityPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Schools near Southview Park from public sources (Propertybook, Wikipedia, The Herald,
 * Global Press Journal), researched 3 October 2026. Listed as public listings only:
 * none is a partner yet, so nothing implies an agreement.
 */
class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $schools = [
            ['Southlea Park Primary School', 'Government primary school next to Southview Park', 'Southlea Park, Harare'],
            ['Tariro Primary School', 'Public primary school serving Hopley and Southview Park', 'Hopley, Harare'],
            ['Glen Norah 1 High School', 'Public secondary school serving Southview Park families', 'Glen Norah, Harare'],
            ['Glen View 1 High School', 'Government secondary school', 'Glen View, Harare'],
            ['Glen View 2 High School', 'Public secondary school', 'Glen View, Harare'],
            ['Glen View 3 High School', 'Public secondary school', 'Glen View, Harare'],
            ['Glen View 5 Primary School', 'Public primary school', 'Glen View, Harare'],
            ['Glen View 8 Primary School', 'Public primary school serving Glen View 8 and 9', 'Glen View 8, Harare'],
            ['Eaglesvale School', 'Private school close to Southview Park', 'Harare South'],
            ['Mbare High School', 'Public secondary school', 'Mbare, Harare'],
        ];
        foreach ($schools as [$name, $tagline, $address]) {
            CommunityPage::updateOrCreate(['slug' => Str::slug($name)], [
                'type' => 'school', 'name' => $name, 'tagline' => $tagline, 'address' => $address, 'verified' => false, 'active' => true,
                'description' => "{$name} is one of the schools that Southview Park families use. This page is a public listing compiled from public sources; the school is not yet a partner of the association. Once a school signs up, parents can pay fees and receive reports and notices here.",
            ]);
        }
    }
}
