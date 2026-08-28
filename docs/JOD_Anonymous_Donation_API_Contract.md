# JOD Anonymous Donation — Frontend API Contract

## Scope

This contract adds anonymous-donation support to the existing manual donation workflow. Anonymous means **publicly anonymous only**: the donation remains linked to the authenticated donor, stays visible in My Donations, remains visible to authorized organization staff, and continues to count toward campaign totals when the organization completes it.

## Data model

All donation responses now include:

```ts
isAnonymous: boolean
```

- `false`: normal donation.
- `true`: donor identity must not be exposed on public-facing surfaces.
- The value is fixed when the donation is created for the MVP.

## 1. Create donation intent

**POST** `/api/mobile/campaigns/{campaignId}/donations`

Authentication: Sanctum mobile access token.

### Request body

```json
{
  "amount": 50,
  "contactMethod": "phone",
  "paymentMethod": "cash",
  "phone": "+962790000000",
  "city": "Amman",
  "notes": "Optional note",
  "isAnonymous": true
}
```

### Validation

| Field | Required | Rules |
|---|---|---|
| `amount` | yes | numeric, 0.01–999999999.99, max 2 decimals |
| `contactMethod` | yes | `phone\|whatsapp\|email\|other` |
| `paymentMethod` | no | `bank_transfer\|cash\|other` |
| `phone` | no | string, max 20 |
| `city` | no | string, max 100 |
| `notes` | no | string, max 2000 |
| `isAnonymous` | no | boolean; defaults to `false` |

### Success response

```json
{
  "success": true,
  "data": {
    "id": "123",
    "campaignId": "campaign-uuid",
    "campaignTitle": "Campaign title",
    "organizationName": "Organization",
    "amount": 50,
    "status": "pending",
    "contactMethod": "phone",
    "paymentMethod": "cash",
    "phone": "+962790000000",
    "city": "Amman",
    "notes": "Optional note",
    "isAnonymous": true,
    "cancelReason": null,
    "source": "mobile_app",
    "createdAt": "2026-08-28T12:40:00+03:00",
    "contactedAt": null,
    "agreedAt": null,
    "completedAt": null,
    "cancelledAt": null,
    "organization": "Organization",
    "donatedAmount": 50,
    "targetAmount": 1000,
    "date": "2026-08-28T12:40:00+03:00",
    "flow": "contributed"
  },
  "message": "Donation intent created successfully. The campaign amount is not updated until the organization confirms receipt."
}
```

### Frontend behavior

Default the donation form to:

```ts
isAnonymous = false
```

When enabled, send `isAnonymous: true`. Keep the chosen value after recoverable request errors so retry does not silently change the donor's privacy choice.

## 2. My Donations

**GET** `/api/mobile/me/donations`

Supported existing query parameters include `flow`, `status`, `campaignId`, and `perPage`.

Every item includes:

```json
{
  "isAnonymous": true
}
```

For the donor's own UI, do not hide amount, campaign, status, dates, or their own stored contact details merely because the donation is anonymous. Show a badge such as **تبرع مجهول**.

## 3. My Donation details

**GET** `/api/mobile/me/donations/{donationId}`

The response uses the same donation shape and includes `isAnonymous`.

Suggested UI label:

- `isAnonymous: true` → **مجهول علنًا**
- `isAnonymous: false` → normal/identified donation

## 4. Organization donation workspace

Base path: `/api/v1/org/donations`

Authentication: protected organization access token and active organization.

### List

**GET** `/api/v1/org/donations`

Existing filters:
- `status`
- `campaignId`
- `perPage`

Authorized organization responses keep operational donor details and now include:

```json
{
  "id": "123",
  "campaignId": "campaign-uuid",
  "campaignTitle": "Campaign title",
  "name": "Donor Name",
  "email": "donor@example.com",
  "phone": "+962790000000",
  "city": "Amman",
  "amount": 50,
  "status": "pending",
  "contactMethod": "phone",
  "paymentMethod": "cash",
  "notes": null,
  "isAnonymous": true,
  "cancelReason": null,
  "createdAt": "2026-08-28T12:40:00+03:00",
  "contactedAt": null,
  "agreedAt": null,
  "completedAt": null,
  "cancelledAt": null
}
```

### Details

**GET** `/api/v1/org/donations/{donationId}`

Same resource shape as the list item, including `isAnonymous`.

### Existing workflow transitions

Anonymous donations use the same workflow and endpoints:

- **PATCH** `/api/v1/org/donations/{donationId}/contact`
- **PATCH** `/api/v1/org/donations/{donationId}/agree`
- **PATCH** `/api/v1/org/donations/{donationId}/complete`
- **PATCH** `/api/v1/org/donations/{donationId}/cancel`

No anonymous-specific workflow endpoint exists.

For the organization UI, keep operational contact data visible to authorized staff and add a badge such as **مجهول علنًا** so staff know the donor identity must not be published.

## 5. Campaign accounting

`isAnonymous` does not change campaign accounting.

- Creating a donation intent does not change campaign totals.
- Completing an anonymous donation updates `raised_amount` exactly like a normal donation.
- Donor-count behavior remains the same as the existing donation workflow.
- Anonymous is not a filter or condition in accounting logic.

## 6. Public privacy rule

Any current or future public-facing donor attribution must enforce privacy in the backend.

When `isAnonymous === true`, public responses must not expose:
- donor name,
- avatar,
- user/profile identifier,
- profile URL/link,
- email,
- phone.

Use **متبرع مجهول** when a display label is needed.

The current implementation keeps donor identity intact for the donor and authorized organization workspace; it does not convert the donation into an unowned/guest record.

## 7. TypeScript changes

```ts
export type CreateDonationPayload = {
  amount: number;
  contactMethod: 'phone' | 'whatsapp' | 'email' | 'other';
  paymentMethod?: 'bank_transfer' | 'cash' | 'other' | null;
  phone?: string | null;
  city?: string | null;
  notes?: string | null;
  isAnonymous?: boolean;
};

export type Donation = {
  // existing fields...
  isAnonymous: boolean;
};

export type OrganizationDonation = {
  // existing fields...
  isAnonymous: boolean;
};
```

## 8. Error handling

Invalid `isAnonymous` values return HTTP 422 using the project's validation error envelope. Frontend clients should send a real JSON boolean, not strings such as `"true"`, `"false"`, `"yes"`, or `"no"`.

## 9. Backward compatibility

- Existing clients that omit `isAnonymous` continue to create normal donations.
- Existing stored donations resolve to `isAnonymous = false` through the database default.
- No existing route or lifecycle status is removed or renamed.
