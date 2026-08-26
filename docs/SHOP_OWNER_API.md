# Shop Owner API for Angular POS

## Base URL

For the default XAMPP folder layout, the API base URL is typically:

```text
http://localhost/Laravel/Inventory/public/api
```

Change this value if the Laravel project is served through a virtual host. Both
shop-owner endpoints are currently public and do not require an authorization
token. Every request should send `Accept: application/json`; POST requests
should also send `Content-Type: application/json`.

## Shop owner object

```json
{
  "id": 1,
  "distributor_id": 12,
  "name": "Rahim Store",
  "email": "rahim@example.com",
  "phones": ["+8801712345678", "01812345678"],
  "latitude": 23.8103,
  "longitude": 90.4125,
  "address": "Dhaka, Bangladesh",
  "created_at": "2026-08-13T12:30:00+00:00"
}
```

## Get shop owners

```http
GET /api/shop-owners?distributor_id=12
```

Optional query parameters:

| Parameter | Type | Default | Rules | Description |
| --- | --- | --- | --- | --- |
| `distributor_id` | integer | none | Required; must exist in `customers` | Returns only this distributor's shop owners |
| `search` | string | none | Maximum 100 characters | Searches name, email, address, and phone numbers |
| `per_page` | integer | `20` | Between 1 and 100 | Number of records on each page |
| `page` | integer | `1` | Positive page number | Page requested by Laravel pagination |

Example:

```http
GET /api/shop-owners?distributor_id=12&search=01712&per_page=20&page=1
```

Success response (`200 OK`):

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "distributor_id": 12,
      "name": "Rahim Store",
      "email": "rahim@example.com",
      "phones": ["+8801712345678", "01812345678"],
      "latitude": 23.8103,
      "longitude": 90.4125,
      "address": "Dhaka, Bangladesh",
      "created_at": "2026-08-13T12:30:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

Records are ordered from newest to oldest.

## Create a shop owner

```http
POST /api/shop-owners
Content-Type: application/json
```

Request body:

```json
{
  "distributor_id": 12,
  "name": "Rahim Store",
  "email": "rahim@example.com",
  "phones": ["+8801712345678", "01812345678"],
  "latitude": 23.8103,
  "longitude": 90.4125,
  "address": "Dhaka, Bangladesh"
}
```

Validation rules:

| Field | Rules |
| --- | --- |
| `distributor_id` | Required integer matching an existing distributor in `customers` |
| `name` | Required string, maximum 255 characters |
| `email` | Required valid and unique email, maximum 255 characters |
| `phones` | Required array containing 1 to 10 numbers |
| `phones.*` | Distinct string, 7 to 30 phone characters; digits, spaces, `+`, `-`, `(`, `)`, and `.` are accepted |
| `latitude` | Required number between -90 and 90 |
| `longitude` | Required number between -180 and 180 |
| `address` | Required string, maximum 2,000 characters |

Success response (`201 Created`):

```json
{
  "success": true,
  "message": "Shop owner information saved successfully.",
  "data": {
    "id": 1,
    "distributor_id": 12,
    "name": "Rahim Store",
    "email": "rahim@example.com",
    "phones": ["+8801712345678", "01812345678"],
    "latitude": 23.8103,
    "longitude": 90.4125,
    "address": "Dhaka, Bangladesh",
    "created_at": "2026-08-13T12:30:00+00:00"
  }
}
```

Validation failure (`422 Unprocessable Entity`):

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "phones.0": ["Each phone number must be a valid phone number."]
  }
}
```

## Angular interfaces

Create `shop-owner.model.ts`:

```typescript
export interface ShopOwner {
  id: number;
  distributor_id: number;
  name: string;
  email: string;
  phones: string[];
  latitude: number;
  longitude: number;
  address: string;
  created_at: string | null;
}

export interface CreateShopOwnerRequest {
  distributor_id: number;
  name: string;
  email: string;
  phones: string[];
  latitude: number;
  longitude: number;
  address: string;
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ShopOwnerListResponse {
  success: boolean;
  data: ShopOwner[];
  meta: PaginationMeta;
}

export interface ShopOwnerCreateResponse {
  success: boolean;
  message: string;
  data: ShopOwner;
}

export interface LaravelValidationError {
  message: string;
  errors: Record<string, string[]>;
}
```

## Angular service

Set the Laravel URL in the Angular environment:

```typescript
export const environment = {
  apiUrl: 'http://localhost/Laravel/Inventory/public/api',
};
```

Create `shop-owner.service.ts`:

```typescript
import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import {
  CreateShopOwnerRequest,
  ShopOwnerCreateResponse,
  ShopOwnerListResponse,
} from './shop-owner.model';

@Injectable({ providedIn: 'root' })
export class ShopOwnerService {
  private readonly http = inject(HttpClient);
  private readonly endpoint = `${environment.apiUrl}/shop-owners`;

  getShopOwners(options: {
    distributorId: number;
    search?: string;
    page?: number;
    perPage?: number;
  }): Observable<ShopOwnerListResponse> {
    let params = new HttpParams().set(
      'distributor_id',
      options.distributorId,
    );

    if (options.search) {
      params = params.set('search', options.search);
    }
    if (options.page) {
      params = params.set('page', options.page);
    }
    if (options.perPage) {
      params = params.set('per_page', options.perPage);
    }

    return this.http.get<ShopOwnerListResponse>(this.endpoint, { params });
  }

  createShopOwner(
    request: CreateShopOwnerRequest,
  ): Observable<ShopOwnerCreateResponse> {
    return this.http.post<ShopOwnerCreateResponse>(this.endpoint, request);
  }
}
```

Ensure Angular's HTTP client is registered in the application. For a standalone
application, add `provideHttpClient()` to the providers in `app.config.ts`.

## Angular component usage

```typescript
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject } from '@angular/core';
import { ShopOwnerService } from './shop-owner.service';
import {
  CreateShopOwnerRequest,
  LaravelValidationError,
  ShopOwner,
} from './shop-owner.model';

@Component({
  selector: 'app-shop-owners',
  templateUrl: './shop-owners.component.html',
})
export class ShopOwnersComponent {
  private readonly shopOwnerService = inject(ShopOwnerService);

  owners: ShopOwner[] = [];
  validationErrors: Record<string, string[]> = {};

  loadOwners(distributorId: number, search = '', page = 1): void {
    this.shopOwnerService
      .getShopOwners({ distributorId, search, page, perPage: 20 })
      .subscribe(response => {
        this.owners = response.data;
      });
  }

  saveOwner(request: CreateShopOwnerRequest): void {
    this.validationErrors = {};

    this.shopOwnerService.createShopOwner(request).subscribe({
      next: response => {
        this.owners = [response.data, ...this.owners];
      },
      error: (error: HttpErrorResponse) => {
        if (error.status === 422) {
          const body = error.error as LaravelValidationError;
          this.validationErrors = body.errors;
        }
      },
    });
  }
}
```

The Laravel project already permits cross-origin requests to `api/*`, so an
Angular development server on another port can call these endpoints.
