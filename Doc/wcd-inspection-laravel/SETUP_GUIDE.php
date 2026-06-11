<?php

// ============================================================
// app/Http/Kernel.php — middlewareAliases madhe he add kara
// ============================================================

// $middlewareAliases array madhe he add kara:
'auth.jwt' => \App\Http\Middleware\JwtAuthMiddleware::class,


// ============================================================
// config/app.php — jwt_secret add kara
// ============================================================

// 'jwt_secret' => env('JWT_SECRET', ''),
// He 'app' array madhe add kara


// ============================================================
// INSTALL STEPS — Laravel project setup
// ============================================================

/*
Step 1 — Laravel install kara (ek vel)
composer create-project laravel/laravel wcd-inspection

Step 2 — JWT library install kara
composer require firebase/php-jwt

Step 3 — Files copy kara
- routes/api.php               → routes/api.php
- app/Http/Middleware/...      → app/Http/Middleware/
- app/Http/Controllers/...     → app/Http/Controllers/
- .env.example → .env          → values fill kara

Step 4 — Kernel.php madhe middleware register kara
app/Http/Kernel.php madhe $middlewareAliases madhe:
'auth.jwt' => \App\Http\Middleware\JwtAuthMiddleware::class,

Step 5 — config/app.php madhe jwt_secret add kara
'jwt_secret' => env('JWT_SECRET', ''),

Step 6 — MySQL tables run kara
database/migrations/create_all_tables.sql → phpMyAdmin madhe run kara

Step 7 — .env madhe DB details fill kara

Step 8 — Server start kara
php artisan serve
*/


// ============================================================
// COMPLETE API ENDPOINTS — Same as Node.js
// ============================================================

/*
POST   /api/super-admin/add
POST   /api/super-admin/login
POST   /api/super-admin/send-otp
POST   /api/super-admin/verify-otp
GET    /api/super-admin/get            [JWT: superadmin]
PUT    /api/super-admin/edit/{id}      [JWT: superadmin]
DELETE /api/super-admin/delete/{id}    [JWT: superadmin]

POST   /api/state-admin/login
POST   /api/state-admin/add            [JWT: superadmin]
GET    /api/state-admin/get            [JWT: superadmin]
PUT    /api/state-admin/edit/{id}      [JWT: superadmin]
DELETE /api/state-admin/delete/{id}    [JWT: superadmin]

POST   /api/district-admin/login
POST   /api/district-admin/add         [JWT: superadmin]
GET    /api/district-admin/get         [JWT: superadmin]
PUT    /api/district-admin/edit/{id}   [JWT: superadmin]
DELETE /api/district-admin/delete/{id} [JWT: superadmin]

POST   /api/inspection-officer/login
POST   /api/inspection-officer/add     [JWT: districtadmin]
GET    /api/inspection-officer/get     [JWT: districtadmin]
PUT    /api/inspection-officer/edit/{id}    [JWT: districtadmin]
DELETE /api/inspection-officer/delete/{id}  [JWT: districtadmin]

POST   /api/org/register
POST   /api/org/login
GET    /api/org/profile                [JWT: organization]
PUT    /api/org/profile/edit           [JWT: organization]
POST   /api/org/survey/submit          [JWT: organization]
GET    /api/org/survey/my              [JWT: organization]

POST   /api/assignment/assign          [JWT: districtadmin]
PUT    /api/assignment/deassign/{id}   [JWT: districtadmin]
GET    /api/assignment/get             [JWT: districtadmin]

GET    /api/officer/assignments        [JWT: inspectionofficer]
POST   /api/officer/report/submit      [JWT: inspectionofficer]
GET    /api/officer/report/get         [JWT: inspectionofficer]
PUT    /api/officer/report/reinspect/{id} [JWT: inspectionofficer]

GET    /api/dashboard/superadmin       [JWT: superadmin]
GET    /api/dashboard/stateadmin       [JWT: stateadmin]
GET    /api/dashboard/districtadmin    [JWT: districtadmin]
GET    /api/dashboard/officer          [JWT: inspectionofficer]
*/
