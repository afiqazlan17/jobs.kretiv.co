<?php

// Mirrors lib/constants.js from the old Next.js app — the canonical
// lookup data referenced from Blade views and controllers.
return [

    'departments' => [
        'print' => ['label' => 'KretivPrint', 'color' => '#E85D04'],
        'work' => ['label' => 'KretivWork', 'color' => '#7209B7'],
        'tech' => ['label' => 'KretivTech', 'color' => '#3A86FF'],
        'machine' => ['label' => 'KretivMachine', 'color' => '#6B7280'],
        'event' => ['label' => 'KretivEvent', 'color' => '#E91E63'],
        'wisb' => ['label' => 'Waffiy Industries', 'color' => '#9B93A8'],
    ],

    'roles' => [
        'bod' => ['label' => 'BOD', 'color' => '#E91E63', 'desc' => 'Full access — all departments, reports, settings'],
        'dept_head' => ['label' => 'Dept Head', 'color' => '#3A86FF', 'desc' => 'Own department(s) — jobs, reports'],
        'staff' => ['label' => 'Staff', 'color' => '#6B7280', 'desc' => 'Own department(s) — jobs, no reports/finance/settings'],
        'intern' => ['label' => 'Intern', 'color' => '#10B981', 'desc' => 'Own department(s) — same access as Staff'],
    ],

    'job_types' => [
        'client_project' => ['label' => 'Client Project', 'color' => '#3A86FF', 'desc' => 'Kretivco does custom work for the customer'],
        'product_sale' => ['label' => 'Product Sale', 'color' => '#10B981', 'desc' => 'Customer buys an existing Kretivco product'],
    ],

    // Pre-packaged products staff can pick directly instead of typing a
    // Product Sale job's items by hand. A product line (e.g. Undangan.my)
    // prices differently depending on who's buying: an end user paying
    // Kretivco directly gets the full bundle (card + banner + signage); a
    // vendor like a wedding planner reselling Kretivco as their printer
    // gets a cheaper, card-only rate. The chosen tier's price and item
    // list get baked into the job's line_items at creation (see
    // JobController::resolvePackageTier()).
    'package_catalog' => [
        'print' => [
            [
                'key' => 'undangan_my',
                'label' => 'Undangan.my',
                'segments' => [
                    [
                        'key' => 'end_user',
                        'label' => 'End User — Direct Customer',
                        'packages' => [
                            [
                                'key' => 'vip',
                                'label' => 'Undangan.my: VIP Wedding Card Package',
                                'items' => [
                                    '1x Undangan.my Digital Wedding Card',
                                    '4x8in Wedding Card Postcard with Envelope',
                                    '1x Banner 3x6ft',
                                    '4x Arrow 2x2ft',
                                    '2x Bunting 2x5ft',
                                ],
                                'tiers' => [
                                    ['pcs' => 100, 'price' => 380],
                                    ['pcs' => 200, 'price' => 410],
                                    ['pcs' => 300, 'price' => 448],
                                    ['pcs' => 400, 'price' => 468],
                                    ['pcs' => 500, 'price' => 485],
                                ],
                            ],
                        ],
                    ],
                    [
                        'key' => 'vendor',
                        'label' => 'Vendor — Wedding Planner',
                        'packages' => [
                            [
                                'key' => 'dloveweddingplanner',
                                'label' => 'Undangan.my: DLoveWeddingPlanner Package',
                                'items' => [
                                    'Digital Card',
                                    'Physical Card {pcs}pcs',
                                    'Welcome Board',
                                ],
                                'tiers' => [
                                    ['pcs' => 100, 'price' => 197],
                                    ['pcs' => 200, 'price' => 274],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'banks' => [
        'mbb' => ['label' => 'Maybank', 'code' => 'MBB', 'color' => '#FFC107'],
        'affin' => ['label' => 'AFFIN', 'code' => 'AFFIN', 'color' => '#E53935'],
    ],

    // A job stays "potential" while it's still a quotation (nothing
    // confirmed yet). Once the customer confirms, staff claim it ("Take In
    // Job") — that single action sets the PIC and moves it straight to
    // "in_progress". It stays there for the whole time the work is
    // actually happening, and moves to "completed" when staff close the
    // ticket.
    'job_statuses' => [
        'potential' => ['label' => 'Potential', 'color' => '#6366F1'],
        'in_progress' => ['label' => 'In Progress', 'color' => '#3A86FF'],
        'completed' => ['label' => 'Completed', 'color' => '#6B7280'],
        'cancelled' => ['label' => 'Cancelled', 'color' => '#EF4444'],
    ],

    // Orthogonal to job_statuses above — a job can be "In Progress" and
    // "Pending" at the same time (e.g. work technically ongoing but
    // waiting on customer confirmation). Visible flag + reason only, no
    // automatic SLA timer.
    'hold_statuses' => [
        'pending' => ['label' => 'Pending', 'color' => '#F59E0B', 'icon' => '⏸'],
        'suspended' => ['label' => 'Suspended', 'color' => '#EF4444', 'icon' => '⛔'],
    ],

    'cancel_reasons' => [
        'customer_cancelled' => 'Customer cancelled',
        'budget_issue' => 'Budget issue',
        'scope_changed' => 'Scope changed',
        'no_response' => 'No response',
        'other' => 'Other',
    ],

    'sources' => [
        'tender' => 'Tender',
        'referral' => 'Referral',
        'walk-in' => 'Walk-in',
        'social_media' => 'Social Media',
        'website' => 'Website',
        'other' => 'Other',
    ],

    // A sole-proprietor still registers an SSM number, so "has SSM" is
    // what actually distinguishes a company from a walk-in/personal
    // customer here — not company size.
    'customer_types' => [
        'individual' => 'Individual',
        'company' => 'Company',
    ],

    'vendor_categories' => [
        'printing' => 'Printing',
        'delivery' => 'Delivery / Logistics',
        'design_freelance' => 'Design / Freelance',
        'event_equipment' => 'Event & Equipment',
        'subcontractor' => 'Subcontractor',
        'other' => 'Other',
    ],

    'lead_stages' => [
        'new' => ['label' => 'New', 'color' => '#6366F1'],
        'contacted' => ['label' => 'Contacted', 'color' => '#F59E0B'],
        'quoted' => ['label' => 'Quoted', 'color' => '#3A86FF'],
        'won' => ['label' => 'Won', 'color' => '#10B981'],
        'lost' => ['label' => 'Lost', 'color' => '#EF4444'],
    ],

    'brand' => [
        'name' => 'Kretivco Mediaworks',
        'ssm' => '(SA0463354-A)',
        'address_line_1' => 'No.15A, Jalan USJ1/19',
        'address_line_2' => '47600, Subang Jaya, Selangor',
        'email' => 'kretivco@gmail.com',
        'phone' => '+6011-21149204',
    ],

    'bank_details' => [
        'mbb' => ['label' => 'MAYBANK', 'acct' => '5621-0668-8317', 'name' => 'KRETIVCO MEDIAWORKS'],
        'affin' => ['label' => 'AFFIN', 'acct' => 'XXXX-XXXX-XXXX', 'name' => 'KRETIVCO MEDIAWORKS'],
    ],

    // Picking a department attributes the cost to that department (cost of
    // service); leaving it blank posts as a company-wide operating expense.
    'expense_categories' => [
        'subcontractor' => 'Subcontractor / Consignment',
        'rent' => 'Rent',
        'utilities' => 'Utilities',
        'salary' => 'Salary',
        'commission' => 'Commission',
        'software' => 'Software & Subscriptions',
        'other' => 'Other',
    ],

];
