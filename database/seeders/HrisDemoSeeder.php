<?php

namespace Database\Seeders;

use App\Models\AttendanceRemark;
use App\Models\CalendarEvent;
use App\Models\DailyAttendance;
use App\Models\Delegation;
use App\Models\Division;
use App\Models\IntegrationRun;
use App\Models\PdsEntry;
use App\Models\PortalItem;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\RawPunch;
use App\Models\ServiceClient;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceService;
use App\Services\EmploymentHistory;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/** Fictional local fixtures inspired by the legacy schema, never a personnel import. */
class HrisDemoSeeder extends Seeder
{
    public function run(): void
    {
        $password = Str::password(24);
        $this->seed($password);
        $this->command?->info('Demo administrator: admin@demo.hris.test');
        $this->command?->info('Password for newly created demo accounts: '.$password);
        $this->command?->info('Existing accounts and passwords were preserved.');
    }

    public function seed(string $password): void
    {
        $this->assertTarget();
        if (strlen($password) < 16) {
            throw new RuntimeException('Supply a generated demo password of at least 16 characters.');
        }

        DB::transaction(function () use ($password) {
            // Keep relative dates stable on subsequent runs, even after the original seed day.
            $existing = User::where('email', 'admin@demo.hris.test')->first();
            $day = $existing?->created_at
                ? CarbonImmutable::instance($existing->created_at)->timezone('Asia/Manila')->startOfDay()
                : CarbonImmutable::today('Asia/Manila');
            $passwordHash = Hash::make($password);
            $divisions = [];
            foreach (['Administration and Finance', 'Human Resources', 'Information Systems', 'Planning and Research', 'Operations and Services'] as $i => $name) {
                $divisions[] = Division::firstOrCreate(['name' => '[Demo] '.$name], ['acronym' => 'DM'.($i + 1)]);
            }

            $users = [];
            for ($i = 1; $i <= 50; $i++) {
                $users[$i] = $this->employee($i, $divisions, $day, $passwordHash);
                $actorId = $users[1]->id;
                $this->personnel($users[$i], $i, $day, $actorId);
                app(EmploymentHistory::class)->backfill($users[$i], $actorId);
            }

            foreach ($divisions as $i => $division) {
                Position::firstOrCreate(['code' => 'DEMO-VAC-'.($i + 1)], [
                    'division_id' => $division->id, 'title' => '[Demo] Project Officer vacancy',
                    'plantilla' => true, 'classification' => 'Staff', 'status' => 'Open for Application',
                ]);
            }
            Delegation::firstOrCreate([
                'user_id' => $users[9]->id, 'division_id' => $users[9]->division_id,
                'ability' => 'attendance.division', 'period_start' => $day->startOfMonth()->toDateString(),
            ], ['period_end' => $day->endOfMonth()->toDateString(), 'actor_id' => $users[2]->id]);

            $this->portal($users, $day);
            $this->attendance($users, $day);
        });
    }

    private function assertTarget(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Demo seeding is restricted to local and isolated test environments.');
        }
        $connection = DB::connection();
        if ($connection->getName() !== DB::getDefaultConnection()) {
            throw new RuntimeException('The database connection name must match its configured alias before demo seeding.');
        }
        if (app()->environment('testing') && $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:') {
            return;
        }
        if (! app()->environment('local')
            || ! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)
            || $connection->getDatabaseName() !== 'db_hris_v2'
            || $connection->selectOne('SELECT DATABASE() AS name')->name !== 'db_hris_v2') {
            throw new RuntimeException('Demo seeding requires the db_hris_v2 database; db_hris must remain read-only.');
        }
    }

    /** @param list<Division> $divisions */
    private function employee(int $i, array $divisions, CarbonImmutable $day, string $passwordHash): User
    {
        $role = match (true) {
            $i === 1 => 'system_admin', $i === 2 => 'hr', $i === 3 => 'executive_director',
            $i <= 8 => 'division_chief', $i >= 49 => 'applicant', default => 'staff',
        };
        $email = match (true) {
            $i === 1 => 'admin', $i === 2 => 'hr', $i === 3 => 'director',
            $i <= 8 => 'chief-'.($i - 3), $i >= 49 => 'applicant-'.($i - 48),
            default => 'staff-'.sprintf('%02d', $i),
        }.'@demo.hris.test';
        $user = User::where('email', $email)->first();
        if ($user) {
            if (! str_starts_with($user->name, 'Demo ')
                && ! DB::table('audit_events')->where('action', 'demo.seed')->where('subject_type', User::class)->where('subject_id', $user->id)->exists()) {
                throw new RuntimeException('A demo email belongs to an unrelated account; no records were committed.');
            }

            return $user;
        }

        $division = $divisions[$i <= 3 ? ($i === 2 ? 1 : 0) : ($i <= 8 ? $i - 4 : ($i - 9) % 5)];
        $type = match (true) {
            $role === 'applicant' => 'Applicant', $i === 45 => 'Probationary',
            $i > 8 && $i % 9 === 0 => 'Intern', $i > 8 && $i % 11 === 0 => 'Contractual Regular',
            $i > 8 && $i % 5 === 0 => 'CSW', default => 'Permanent',
        };
        $title = match ($role) {
            'system_admin' => 'System Administrator', 'hr' => 'Human Resource Officer',
            'executive_director' => 'Executive Director', 'division_chief' => 'Division Chief',
            default => ['Administrative Assistant', 'Research Analyst', 'Information Systems Officer', 'Planning Officer', 'Program Officer'][$i % 5],
        };
        $position = $role === 'applicant' ? null : Position::firstOrCreate(['code' => 'DEMO-POS-'.sprintf('%03d', $i)], [
            'division_id' => $division->id, 'title' => '[Demo] '.$title,
            'plantilla' => $type === 'Permanent', 'classification' => $i <= 8 ? 'Supervisory' : 'Staff',
            'status' => $i >= 46 ? 'Open for Application' : 'Filled Up',
        ]);
        $first = ['Ava', 'Leo', 'Maya', 'Noah', 'Isla', 'Eli', 'Nina', 'Theo', 'Luna', 'Kai'][($i - 1) % 10];
        $last = ['Rivera', 'Santos', 'Reyes', 'Cruz', 'Garcia'][intdiv($i - 1, 10)];
        $user = new User;
        $user->forceFill([
            'name' => 'Demo '.$first.' '.$last, 'first_name' => 'Demo '.$first,
            'last_name' => $last, 'middle_name' => 'Sample', 'nickname' => $first,
            'email' => $email, 'password' => $passwordHash, 'email_verified_at' => now(),
            'role' => $role, 'employee_type' => $type,
            'division_id' => $role === 'applicant' ? null : $division->id, 'position_id' => $position?->id,
            'atms_id' => $role === 'applicant' ? null : (string) (990000000 + $i),
            'date_hired' => $role === 'applicant' ? null : $day->subYears(3)->subDays($i)->toDateString(),
            'termination_date' => $i === 48 ? $day->subMonths(2)->toDateString() : null,
            'is_archived' => in_array($i, [46, 47], true),
            'workweek' => $role === 'applicant' ? null : ($type === 'Intern' || $i % 6 === 0 ? 5 : 4),
            'step_increment' => $role === 'applicant' ? null : 1 + ($i % 8),
            'birthday' => $day->subYears(25 + $i % 20)->addDays($i % 10)->toDateString(),
            'sex' => $i % 2 === 0 ? 'Male' : 'Female',
            'contact_person' => 'Demo Emergency Contact '.sprintf('%02d', $i),
            // No routable phone numbers or real government identifiers in fixtures.
            'mobile_no' => null, 'contact_person_no' => null,
        ])->save();
        Audit::record('demo.seed', $user, null, reason: 'Fictional local sample; not imported personnel.');

        return $user;
    }

    private function personnel(User $user, int $i, CarbonImmutable $day, int $actorId): void
    {
        if ($user->position_id !== null) {
            PositionAssignment::firstOrCreate(['user_id' => $user->id, 'position_id' => $user->position_id], [
                'old_position_id' => null, 'actor_id' => $actorId, 'assume_date' => $user->date_hired->toDateString(),
            ]);
            WorkSchedule::firstOrCreate(['user_id' => $user->id, 'starts_on' => $user->date_hired->toDateString()], [
                'ends_on' => $user->termination_date?->toDateString(), 'workweek' => $user->workweek,
                'policy_version' => 'office-v1', 'actor_id' => $actorId,
            ]);
        }

        $sections = [
            'personal' => [
                'first_name' => $user->first_name, 'middle_name' => $user->middle_name, 'last_name' => $user->last_name,
                'birthday' => $user->birthday->toDateString(), 'sex' => $user->sex, 'citizenship' => 'Filipino',
                'civil_status' => $i % 3 === 0 ? 'Married' : 'Single', 'height' => 160 + $i % 20, 'weight' => 55 + $i % 25,
                'employee_no' => 'DEMO-'.sprintf('%03d', $i), 'contact_person' => $user->contact_person,
                'home_address' => $i.' Example Street, Demo City (fictional)', 'res_city' => 'Demo City',
                'place_of_birth' => 'Demo City (fictional)',
            ],
            'family' => [
                'father_fname' => 'Demo Parent', 'father_lname' => $user->last_name,
                'mother_fname' => 'Demo Parent', 'mother_lname' => 'Example',
                'children' => $i % 3 === 0 ? [['name' => 'Demo Child '.$i, 'birthday' => $day->subYears(6)->toDateString()]] : [],
            ],
            'education' => [
                'educ_level' => 'COLLEGE', 'school' => 'Demo State College (fictional)',
                'course' => $i % 2 ? 'BS Information Systems' : 'BS Public Administration',
                'date_from' => 2012, 'date_to' => 2016, 'year_graduated' => 2016,
            ],
            'eligibility' => [
                'eligibility' => 'Demo professional eligibility (not a real qualification)', 'rating' => '85.00',
                'exam_date' => '2018-03-18', 'exam_place' => 'Demo Testing Center', 'license_no' => 'DEMO-ELIG-'.$i,
            ],
            'experience' => [
                'date_from' => $user->date_hired?->toDateString() ?? '2019-01-01',
                'date_to' => $user->termination_date?->toDateString() ?? ($user->role === 'applicant' ? '2020-12-31' : null),
                'is_ongoing' => $user->role !== 'applicant' && $user->termination_date === null,
                'position' => $user->position()->value('title') ?? 'Demo Trainee', 'department' => 'Demo Organization (fictional)',
                'salary' => 25000 + $i * 250, 'step_increment' => (string) ($user->step_increment ?? 1),
                'appointment_status' => $user->employee_type, 'govt_service' => false,
            ],
            'voluntary' => [
                'org_name_address' => 'Demo Community Learning Group, Demo City',
                'date_from' => '2020-01-10', 'date_to' => '2020-01-11', 'hours' => 8, 'position' => 'Demo volunteer',
            ],
            'learning' => [
                'ld_title' => '[Demo] Data Privacy Orientation', 'date_from' => $day->subMonths(3)->toDateString(),
                'date_to' => $day->subMonths(3)->toDateString(), 'hours' => 8, 'ld_type' => '3', 'conducted_by' => 'Demo Training Center',
            ],
            'other' => [
                'special_skills_hobbies' => 'Spreadsheets, community teaching, reading',
                'non_academic_distinctions_recognition' => 'Demo service recognition (fictional)',
                'membership_association_organization' => 'Demo Learning Circle',
            ],
            'declarations' => array_fill_keys(['34_a', '35_a', '35_b', '36_a', '37_a', '38_a', '38_b', '39_a', '40_a', '40_b', '40_c'], 'No'),
            'references' => ['name_1' => 'Demo Reference One', 'add_1' => 'Demo City (fictional)', 'name_2' => 'Demo Reference Two', 'name_3' => 'Demo Reference Three'],
            'government-id' => ['gov_id' => 'Demo ID - NOT VALID', 'id_no' => 'DEMO-NOT-VALID-'.$i, 'date_issued' => '2020-01-01', 'place_issued' => 'Demo City'],
        ];
        foreach ($sections as $section => $data) {
            // Do not overwrite PDS edits or resurrect deleted entries on repeat runs.
            if (! PdsEntry::withTrashed()->where('user_id', $user->id)->where('section', $section)->exists()) {
                PdsEntry::create(['user_id' => $user->id, 'section' => $section, 'data' => $data, 'actor_id' => $actorId]);
                if ($section === 'education') {
                    PdsEntry::create(['user_id' => $user->id, 'section' => $section, 'actor_id' => $actorId, 'data' => [
                        'educ_level' => 'SECONDARY', 'school' => 'Demo Secondary School (fictional)',
                        'date_from' => 2008, 'date_to' => 2012, 'year_graduated' => 2012,
                    ]]);
                }
                if ($section === 'learning') {
                    PdsEntry::create(['user_id' => $user->id, 'section' => $section, 'actor_id' => $actorId, 'data' => [
                        'ld_title' => '[Demo] Workplace Safety', 'hours' => 4, 'ld_type' => '3', 'conducted_by' => 'Demo Training Center',
                    ]]);
                }
            }
        }
    }

    /** @param array<int, User> $users */
    private function portal(array $users, CarbonImmutable $day): void
    {
        $items = [
            ['application', '[Demo] Staff Learning Hub', 'Explore sample learning resources.', 'https://example.com/learning'],
            ['application', '[Demo] Employee Self Service', 'Sample employee application directory.', 'https://example.com/employees'],
            ['link', '[Demo] Staff Handbook', 'Example handbook link for portal testing.', 'https://example.com/handbook'],
            ['link', '[Demo] Service Desk', 'Example support link for portal testing.', 'https://example.com/support'],
            ['wellness', '[Demo] Take a movement break', 'Pause regularly and stretch comfortably.', null],
            ['wellness', '[Demo] Make time for rest', 'Plan a short break between focused tasks.', null],
            ['announcement', '[Demo] Welcome to the HRIS preview', 'All people and records in this sample dataset are fictional.', null],
            ['announcement', '[Demo] Upcoming staff orientation', 'Sample announcement for the current month.', null],
        ];
        foreach ($items as [$kind, $title, $description, $url]) {
            PortalItem::firstOrCreate(['kind' => $kind, 'title' => $title, 'author_id' => $users[2]->id], [
                'description' => $description, 'url' => $url, 'status' => 'Active',
                'starts_on' => $day->startOfMonth()->toDateString(), 'ends_on' => $day->addMonths(2)->endOfMonth()->toDateString(),
            ]);
        }
        PortalItem::firstOrCreate(['kind' => 'announcement', 'title' => '[Demo] Inactive announcement', 'author_id' => $users[2]->id], [
            'description' => 'A hidden item for testing status filters.', 'status' => 'Inactive',
        ]);

        foreach ([
            [2, 'Office', 'Staff orientation', 2, false],
            [3, 'Executive Director Activities', 'Planning workshop', 4, true],
            [9, 'My Calendar', 'Personal planning block', 0, false],
            [10, 'Work From Home', 'Remote work day', 1, true],
            [2, 'Office', 'Completed team briefing', -3, false],
        ] as [$owner, $type, $title, $offset, $allDay]) {
            $participants = $type === 'Office' ? [$users[9]->id, $users[10]->id, $users[11]->id] : [];
            $this->calendarEvent($users[$owner], $type, '[Demo] '.$title, $day->addDays($offset), $allDay, $participants);
        }
        IntegrationRun::firstOrCreate(['partner' => 'demo-offline', 'actor_id' => $users[1]->id, 'status' => 'succeeded'], [
            'message' => 'Synthetic local status example. No partner request was made.',
        ]);
        IntegrationRun::firstOrCreate(['partner' => 'demo-offline', 'actor_id' => $users[1]->id, 'status' => 'failed'], [
            'message' => 'Synthetic failure example for reviewing the integration history.',
        ]);
    }

    /**
     * @param  list<int>  $participants
     */
    private function calendarEvent(User $owner, string $type, string $title, CarbonImmutable $day, bool $allDay, array $participants): void
    {
        $event = CalendarEvent::firstOrNew(['user_id' => $owner->id, 'title' => $title]);
        if (! $event->exists) {
            $start = $day->setTime($allDay ? 0 : 9, 0);
            $event->fill([
                'division_id' => $owner->division_id, 'event_type' => $type,
                'description' => 'Fictional calendar event; no invitations are sent by the seeder.', 'all_day' => $allDay,
                'starts_at' => $start, 'ends_at' => $allDay ? $start : $start->addHours(2),
            ])->save();
            $event->participants()->sync($participants);
        }
    }

    /** @param array<int, User> $users */
    private function attendance(array $users, CarbonImmutable $day): void
    {
        $client = ServiceClient::firstOrCreate(['name' => '[Demo] Offline attendance fixture'], [
            'token_hash' => hash('sha256', Str::random(64)), 'scopes' => ['attendance.ingest'],
            'devices' => ['DEMO-READER'], 'revoked_at' => now(),
        ]);
        $service = app(AttendanceService::class);
        foreach ($users as $i => $user) {
            if ($user->role === 'applicant' || $user->is_archived || $user->termination_date !== null) {
                continue;
            }
            for ($offset = 28; $offset >= 1; $offset--) {
                $date = $day->subDays($offset);
                if ($date->isWeekend() || ($user->workweek === 4 && $date->isFriday())) {
                    continue;
                }
                $pattern = ($i + $offset) % 12;
                $wfh = $pattern === 2;
                $end = $user->workweek === 4 ? '19:00:00' : '17:00:00';
                $times = match ($pattern) {
                    0 => ['08:05:00'], // Missing clock-out, retained for review.
                    1 => ['09:20:00', '12:00:00', '13:00:00', $end], // Late arrival.
                    2 => ['07:00:00', $user->workweek === 4 ? '18:00:00' : '16:00:00'],
                    3 => ['08:00:00', '12:00:00', '13:00:00', '15:30:00'], // Early exit.
                    4 => ['13:10:00', $end], // Afternoon only.
                    default => ['08:00:00', '12:00:00', '13:00:00', $end],
                };
                foreach ($times as $slot => $time) {
                    RawPunch::firstOrCreate(['source_key' => 'demo:v1:'.$i.':'.$date->toDateString().':'.$slot], [
                        'user_id' => $user->id, 'service_client_id' => $client->id, 'source_epoch' => 'demo-v1',
                        'atms_id' => $user->atms_id, 'punched_at' => $date->toDateString().' '.$time,
                        'device' => $wfh ? 'WEB WFH' : 'DEMO-READER', 'record_type' => 'demo',
                        'wfh_location' => $wfh ? ['label' => 'Fictional remote location'] : null,
                    ]);
                }
                // Existing corrections and recalculations remain under the application's control.
                if (! DailyAttendance::where('user_id', $user->id)->whereDate('attendance_date', $date)->exists()) {
                    $service->rebuild($user, $date->toDateString(), $users[2]->id, 'Fictional demo attendance calculation');
                    if ($i === 9 && $pattern === 0) {
                        $service->override($user, $date->toDateString(), [
                            'time_in' => '08:05:00', 'lunch_out' => '12:00:00', 'lunch_in' => '13:00:00',
                            'time_out' => $end, 'ot_in' => null, 'ot_out' => null, 'workweek' => $user->workweek,
                            'notes' => 'Demo correction of a missing clock-out.',
                        ], $users[2]->id, 'Demo only: reviewed missing clock-out correction');
                    }
                }
            }
            AttendanceRemark::firstOrCreate(['user_id' => $user->id, 'coverage' => $day->startOfMonth()->toDateString()], [
                'fc_absence' => $i % 10 === 0 ? 1 : 0, 'remarks' => 'Fictional demo month; not an official attendance or payroll record.',
                'actor_id' => $users[2]->id,
            ]);
        }
    }
}
