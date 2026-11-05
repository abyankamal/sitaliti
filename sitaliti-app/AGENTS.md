# Developer & Agent Guidelines — SI-TALITI v1.0

Read `CONSTRAINTS.md` before writing code. Do not weaken it to make a change pass.

## Architecture & Context
- Framework: Laravel 12 (Backend) + Inertia.js (Vue 3 Frontend) + MySQL 8.0
- Design Acuan: `../Master_Blueprint_Backend_SITALITI.md`
- Target Lokus: Pre-Audit SPJ Kecamatan Tarogong Kidul & 5 Kelurahan
- Key Contracts:
  - `app/Contracts/BudgetReservationServiceInterface.php`
  - `app/Contracts/TaxCalculationServiceInterface.php`
  - `app/Contracts/RakImporterServiceInterface.php`
  - `app/Contracts/VerificationServiceInterface.php`
