# Daily shop visit API

## Request

```http
GET /api/customer/shop-visits
Authorization: Bearer <customer-login-token>
Accept: application/json
```

Returns the authenticated sales representative's shops whose weekly `visit_day`
matches today in their business timezone (falling back to the application
timezone). For example, on Sunday it returns their shops with `visit_day=sunday`.
Set a shop's Visit Day through the shop owner create/edit form to include it in
the schedule. Shops without a visit day are excluded.

The customer ID comes from the token; a `distributor_id` parameter cannot select
another representative's shops. Only SR accounts can access this endpoint.

Optional query parameters:

| Parameter | Default | Validation | Purpose |
| --- | --- | --- | --- |
| `date` | Today in the business timezone | Valid date in `YYYY-MM-DD` format | Retrieve another day's schedule and visit status |
| `per_page` | `20` | Integer from 1 to 100 | Shops per page |
| `page` | `1` | Positive integer | Page number |

```http
GET /api/customer/shop-visits?date=2026-10-18&per_page=20&page=1
```

## Response (`200 OK`)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "distributor_id": 12,
      "name": "Rahim Store",
      "email": "rahim@example.com",
      "phones": ["01711111111", "01811111111"],
      "latitude": 23.8103,
      "longitude": 90.4125,
      "address": "Dhaka, Bangladesh",
      "created_at": "2026-10-01T09:00:00+00:00",
      "road_name": "Mirpur Road",
      "visit_day": "sunday",
      "is_visited": true,
      "visit_id": 55,
      "visited_at": "2026-10-18T09:30:00+06:00",
      "distance_meters": 2.5
    }
  ],
  "meta": {
    "date": "2026-10-18",
    "day": "Sunday",
    "visit_day": "sunday",
    "timezone": "Asia/Dhaka",
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1,
    "visited": 1,
    "pending": 0
  }
}
```

Shops are ordered by road name, shop name, and ID. `meta.total`, `meta.visited`,
and `meta.pending` cover the whole selected day's schedule across all pages.
Visit status uses only visits recorded by the authenticated SR on the selected
date. Visits on other dates do not mark a shop as visited.

For pending shops, `is_visited` is `false` and `visit_id`, `visited_at`, and
`distance_meters` are `null`. An empty schedule returns `data: []` and zero counts.
This is a weekly schedule, so requesting a past date uses the shops' current
weekly assignments.

The existing `POST /api/customer/shop-visits` records a visit using the SR's GPS
coordinates when they are within 10 meters of a shop. Fetch this GET endpoint
again after recording a visit to refresh its status.

Errors: `401` for missing/invalid authentication, `403` for non-SR accounts, and
`422` for invalid date or pagination parameters.

## Database prerequisite

The existing migration
`2026_10_18_000001_add_road_name_and_visit_day_to_shop_owners_table.php` must be
applied. Follow the project's normal migration process if it is still pending.
