# HimRishtey API

Laravel 13 REST API for the HimRishtey matrimonial applications. It uses PHP 8.3, Laravel Sanctum, MySQL/MariaDB, and Razorpay.

## Architecture

The central database stores `applications` and Sanctum tokens. Each application/brand has a separate legacy database containing members, profiles, wallets, interests, and related data. Every request must provide `X-App-Code`; the `ResolveApplication` middleware uses it to select the application database before authentication.

## Setup

Requirements: PHP 8.3+, Composer, MySQL/MariaDB, and optionally Node.js/npm.

```bash
git clone <repository-url>
cd himrishteyapi
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` (including `DB_*`, `RAZORPAY_KEY_ID`, and `RAZORPAY_KEY_SECRET`), then run:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

## Request conventions

Base URL:

```text
http://127.0.0.1:8000/api/v1
```

All API requests require:

```http
Accept: application/json
X-App-Code: himrishtey
```

JSON requests also use `Content-Type: application/json`. Protected endpoints require:

```http
Authorization: Bearer <token-from-login>
```

Examples below use:

```bash
BASE_URL=http://127.0.0.1:8000/api/v1
APP_CODE=himrishtey
TOKEN=your-access-token
```

## API reference

The **Sample input** column shows a JSON body, query string, form field, or path value. `—` means no input is required beyond headers.

### Authentication and registration

| Method | Endpoint | Auth | Sample input |
|---|---|---:|---|
| POST | `/auth/register` | No | `{"profile_created_for":"Self","full_name":"Aarav Sharma","email":"aarav@example.com","mobile_number":"9876543210","gender":"Male","birth_date_time":"1995-06-15","password":"secret123"}` |
| POST | `/auth/register/step-2/{memberId}` | No | `{"birth_time":"10:30 AM","height":"5.8","country_living_in":"India","state_living_in":"Delhi","city_living_in":"New Delhi"}` |
| POST | `/auth/register/step-3/{memberId}` | No | `{"education":"MBA","employed_in":"Private","occupation":"Manager","annual_income":"1200000"}` |
| POST | `/auth/register/step-4/{memberId}` | No | `{"marital_status":"Never Married","mother_tongue":"Hindi","religion":"Hindu","cast":"Brahmin","manglik":"No","horoscope_needed":"No"}` |
| POST | `/auth/register/step-5/{memberId}` | No | Multipart: `photo=@profile.jpg` (image, max 5 MB) |
| POST | `/auth/login` | No | `{"login":"HIM123","password":"secret123"}`; login may be profile ID, email, or mobile |
| PUT | `/change-password` | Yes | `{"current_password":"secret123","new_password":"new-secret123"}` |

Registration example:

```bash
curl -X POST "$BASE_URL/auth/register" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" \
  -d '{"profile_created_for":"Self","full_name":"Aarav Sharma","email":"aarav@example.com","mobile_number":"9876543210","gender":"Male","birth_date_time":"1995-06-15","password":"secret123"}'
```

Use the returned member ID for steps 2–5:

```bash
curl -X POST "$BASE_URL/auth/register/step-2/123" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" \
  -d '{"birth_time":"10:30 AM","height":"5.8","country_living_in":"India","state_living_in":"Delhi","city_living_in":"New Delhi"}'

curl -X POST "$BASE_URL/auth/register/step-5/123" \
  -H "Accept: application/json" -H "X-App-Code: $APP_CODE" \
  -F "photo=@/absolute/path/profile.jpg"
```

Login example:

```bash
curl -X POST "$BASE_URL/auth/login" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" \
  -d '{"login":"HIM123","password":"secret123"}'
```

### Profile

| Method | Endpoint | Sample input / accepted fields |
|---|---|---|
| GET | `/profile` | — |
| PUT | `/profile/basic` | `profile_created_for`, `full_name`, `mobile_number`, `alternate_number`, `whatsapp_number`, `birth_date_time`, `height`, `gender`, `blood_group`, `health_info`, `birth_place` |
| PUT | `/profile/personal` | `birth_date_time`, `birth_place`, `gender`, `height`, `blood_group`, `marital_status`, `no_of_child`, `health_info` |
| PUT | `/profile/religion` | `religion`, `mother_tongue`, `cast`, `sub_cast`, `gotra`, `manglik` |
| PUT | `/profile/education-career` | `about_my_education`, `education`, `any_other_qualifications`, `about_my_career`, `employed_in`, `occupation`, `designation`, `organization_name`, `job_location`, `annual_income` |
| PUT | `/profile/location` | `country_living_in`, `state_living_in`, `city_living_in`, `address_living_in`, `native_place` |
| PUT | `/profile/family` | `family_type`, `family_status`, `father_name`, `father_occupation`, `mother_name`, `mother_occupation`, `no_of_brothers`, `no_of_sisters`, `married_brothers`, `married_sisters`, `family_income`, `about_family` |
| PUT | `/profile/lifestyle` | `diet`, `is_drinking`, `is_smoking`, `about_me`, `any_disability` |
| PUT | `/profile/partner-preferences` | `looking_for`, `partner_age_from`, `partner_age_to`, `partner_country`, `partner_religion`, `partner_cast`, `partner_height_from`, `partner_height_to`, `partner_education`, `partner_mothertongue`, `partner_annual_income_from`, `partner_annual_income_to`, `is_partner_manglik`, `partner_occupation`, `partner_state`, `partner_city`, `partner_diet`, `is_partner_smoking`, `is_partner_drinking`, `horoscope_needed`, `about_my_partner` |
| POST | `/profile/photos/gallery` | Multipart: `photo=@gallery.jpg` |
| POST | `/profiles/{profileId}/contact/unlock` | Unlock contact details using the database-configured `profile_ranges` rate |

Sample protected requests:

```bash
curl "$BASE_URL/profile" -H "Accept: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN"

curl -X PUT "$BASE_URL/profile/personal" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN" \
  -d '{"height":"5.8","blood_group":"B+","marital_status":"Never Married","no_of_child":0}'

curl -X PUT "$BASE_URL/profile/partner-preferences" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN" \
  -d '{"looking_for":"Bride","partner_age_from":24,"partner_age_to":30,"partner_religion":"Hindu","partner_diet":"Vegetarian"}'

curl -X POST "$BASE_URL/profile/photos/gallery" \
  -H "Accept: application/json" -H "X-App-Code: $APP_CODE" \
  -H "Authorization: Bearer $TOKEN" -F "photo=@/absolute/path/gallery.jpg"

# Unlock member 456's contact details; repeated requests do not charge again
curl -X POST "$BASE_URL/profiles/456/contact/unlock" \
  -H "Accept: application/json" -H "X-App-Code: $APP_CODE" \
  -H "Authorization: Bearer $TOKEN"
```

#### Unlock contact details

This endpoint reveals a target member's email and phone numbers after deducting coins from the authenticated member's wallet.

```http
POST /api/v1/profiles/{profileId}/contact/unlock
```

`profileId` is the target member's numeric database ID, not the public ID such as `HIM123`. No request body is required.

The charge is calculated dynamically:

1. Count the distinct profiles previously unlocked by the authenticated member.
2. Add one to obtain the current profile-view number.
3. Find the row in `profile_ranges` where that number is between `range_from` and `range_to`.
4. Deduct the row's `rate` from the latest wallet balance.
5. Record the deduction in `member_wallet` and the unlock in `profile_viewed` within one transaction.

Rates are never hard-coded in the API. Administrators can change `range_from`, `range_to`, and `rate` in `profile_ranges`. If no range covers the current view number, the API returns HTTP `422` without deducting coins.

Successful first unlock:

```json
{
  "success": true,
  "message": "Contact details unlocked successfully.",
  "data": {
    "profile": {
      "id": 456,
      "profile_id": "HIM456",
      "full_name": "Ananya Sharma"
    },
    "contact_details": {
      "email": "ananya@example.com",
      "mobile_number": "9876543210",
      "alternate_number": null,
      "whatsapp_number": "9876543210"
    },
    "already_unlocked": false,
    "coins_deducted": 10,
    "wallet_balance": 90,
    "profile_view_number": 1,
    "price_range": {
      "from": 1,
      "to": 20
    }
  }
}
```

Unlocking the same profile again does not deduct coins. The response returns `already_unlocked: true`, `coins_deducted: 0`, and the contact details again.

Insufficient wallet balance returns HTTP `402`:

```json
{
  "success": false,
  "message": "Insufficient wallet balance.",
  "data": {
    "required_coins": 10,
    "wallet_balance": 5,
    "profile_view_number": 1
  }
}
```

Other possible errors include attempting to unlock your own profile (`422`), a missing/inactive/hidden profile (`404`), and missing pricing configuration (`422`).

### Search and home

| Method | Endpoint | Sample input |
|---|---|---|
| GET | `/search/quick` | `?age_from=24&age_to=30&religion=Hindu&cast=Brahmin&marital_status=Never%20Married` (at least one filter required) |
| GET | `/search/profile/{profileId}` | Path example: `/search/profile/HIM123` |
| GET | `/search/advanced` | `?age_from=24&age_to=30&religion=Hindu&education=MBA&state_living_in=Delhi` |
| GET | `/home` | — |

Advanced search fields: `age_from`, `age_to`, `religion`, `mother_tongue`, `cast`, `sub_cast`, `gotra`, `manglik`, `marital_status`, `height_from`, `height_to`, `blood_group`, `no_of_child`, `education`, `employed_in`, `occupation`, `designation`, `annual_income`, `country_living_in`, `state_living_in`, `city_living_in`, `native_place`, `diet`, `is_drinking`, `is_smoking`, `any_disability`, `horoscope_needed`.

```bash
curl -G "$BASE_URL/search/advanced" \
  -H "Accept: application/json" -H "X-App-Code: $APP_CODE" \
  -H "Authorization: Bearer $TOKEN" \
  --data-urlencode "age_from=24" --data-urlencode "age_to=30" \
  --data-urlencode "religion=Hindu" --data-urlencode "education=MBA"
```

### Membership and feedback

| Method | Endpoint | Sample input |
|---|---|---|
| GET | `/memberships` | — |
| GET | `/memberships/{membershipTypeId}/plans` | Path example: `/memberships/1/plans` |
| POST | `/rate-us` | `{"rating":5,"description":"Very helpful application."}` |

```bash
curl "$BASE_URL/memberships/1/plans" -H "Accept: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN"

curl -X POST "$BASE_URL/rate-us" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN" \
  -d '{"rating":5,"description":"Very helpful application."}'
```

### Wallet and Razorpay

| Method | Endpoint | Sample input |
|---|---|---|
| GET | `/wallet` | — |
| GET | `/wallet/transactions` | — |
| POST | `/wallet/add-money/order` | `{"amount":500}` (INR; minimum 1, maximum 100000) |
| POST | `/wallet/add-money/verify` | `{"razorpay_order_id":"order_xxx","razorpay_payment_id":"pay_xxx","razorpay_signature":"signature_xxx"}` |

```bash
curl -X POST "$BASE_URL/wallet/add-money/order" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN" \
  -d '{"amount":500}'

curl -X POST "$BASE_URL/wallet/add-money/verify" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN" \
  -d '{"razorpay_order_id":"order_xxx","razorpay_payment_id":"pay_xxx","razorpay_signature":"signature_xxx"}'
```

### Success stories

| Method | Endpoint | Sample input |
|---|---|---|
| GET | `/success-stories` | Approved stories |
| POST | `/success-stories` | Multipart: `groom_name`, `bride_name`, `detail`, optional `photo` |
| GET | `/success-stories/{id}` | Path example: `/success-stories/1` |
| PUT | `/success-stories/{id}` | Optional `groom_name`, `bride_name`, `detail`, `photo` |
| DELETE | `/success-stories/{id}` | Path example: `/success-stories/1` |

```bash
curl -X POST "$BASE_URL/success-stories" \
  -H "Accept: application/json" -H "X-App-Code: $APP_CODE" \
  -H "Authorization: Bearer $TOKEN" \
  -F "groom_name=Aarav" -F "bride_name=Ananya" \
  -F "detail=We met through HimRishtey." -F "photo=@/absolute/path/couple.jpg"

# Method spoofing allows a multipart file with the PUT route
curl -X POST "$BASE_URL/success-stories/1" \
  -H "Accept: application/json" -H "X-App-Code: $APP_CODE" \
  -H "Authorization: Bearer $TOKEN" \
  -F "_method=PUT" -F "detail=Our updated story." -F "photo=@/absolute/path/new.jpg"
```

> **Known route issue:** `GET /success-stories` is declared twice in `routes/api.php`, once for `index` and once for `myStories`. There is currently no distinct URL through which clients can reliably request `myStories`.

### Profile deletion

| Method | Endpoint | Sample input |
|---|---|---|
| POST | `/profile/delete-request` | `{"reason":"I no longer need my account."}` |
| GET | `/profile/delete-request` | — |

```bash
curl -X POST "$BASE_URL/profile/delete-request" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN" \
  -d '{"reason":"I no longer need my account."}'
```

### Likes, shortlists, and interests

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/profile-likes` | List profiles liked by current member |
| GET | `/profile-likes/{memberId}` | Check like status for target member |
| POST | `/profile-likes/{memberId}` | Like target member |
| DELETE | `/profile-likes/{memberId}` | Remove like |
| GET | `/shortlisted` | List shortlisted profiles |
| GET | `/shortlisted/{profileId}` | Check shortlist status |
| POST | `/shortlisted/{profileId}` | Add target member to shortlist |
| DELETE | `/shortlisted/{profileId}` | Remove target member from shortlist |
| POST | `/interests/{profileId}` | Send interest to target member |
| GET | `/interests/sent` | List sent interests |
| GET | `/interests/received` | List received interests |
| PUT | `/interests/{id}/accept` | Accept received interest record |
| PUT | `/interests/{id}/reject` | Reject received interest record |
| DELETE | `/interests/{id}/cancel` | Cancel sent interest record |
| GET | `/interests/{profileId}` | Check interest status with target member |

`memberId` and `profileId` in these interaction routes are numeric member IDs. `id` in accept/reject/cancel is the numeric `sent_interests` record ID.

```bash
# Like member 456
curl -X POST "$BASE_URL/profile-likes/456" -H "Accept: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN"

# Shortlist member 456
curl -X POST "$BASE_URL/shortlisted/456" -H "Accept: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN"

# Send interest to member 456, then accept interest record 10
curl -X POST "$BASE_URL/interests/456" -H "Accept: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN"
curl -X PUT "$BASE_URL/interests/10/accept" -H "Accept: application/json" \
  -H "X-App-Code: $APP_CODE" -H "Authorization: Bearer $TOKEN"
```

### Public legal and diagnostic APIs

These require `X-App-Code` but no bearer token.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/privacy-policy` | Privacy policy |
| GET | `/refund-cancellation` | Refund and cancellation policy |
| GET | `/terms-and-conditions` | Terms and conditions |
| GET | `/about-us` | About content |
| GET | `/test` | Application/database connection test |
| GET | `/test-member-schema` | Development member schema diagnostic |

```bash
curl "$BASE_URL/privacy-policy" -H "Accept: application/json" -H "X-App-Code: $APP_CODE"
curl "$BASE_URL/test" -H "Accept: application/json" -H "X-App-Code: $APP_CODE"
```

`test-member-schema` exposes database metadata and member data and should not be available in production.

## Response format

Responses generally follow:

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

Common HTTP statuses:

| Status | Meaning |
|---:|---|
| 400 | Missing/invalid application header or invalid request state |
| 401 | Invalid credentials or missing/invalid token |
| 403 | Operation not permitted |
| 404 | Resource not found |
| 409 | Duplicate or conflicting record |
| 422 | Validation failed |
| 500 | Server/payment error |
| 503 | Application database unavailable |

## Development

```bash
composer test
./vendor/bin/pint
```

## Security notes

- Legacy member passwords are currently compared as plaintext. Migrating them to secure password hashes is a priority.
- Never commit `.env`, database credentials, Razorpay secrets, access tokens, or customer information.
- Remove or protect diagnostic endpoints before production deployment.
