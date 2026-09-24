# Permission Matrix V3 - RAFA Rental System

Matriks hak akses lengkap berdasarkan tiga role: **USER**, **ADMIN**, **OWNER**.

Legenda:
- **Y** = Diizinkan
- **N** = Dilarang
- **Own** = Hanya data milik sendiri
- **All** = Seluruh data lintas user

---

## 1. Auth & Profile

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Login | Y | Y | Y |
| View Own Profile | Y | Y | Y |
| Update Own Profile | Y | Y | Y |
| Upload Identity Document (KTP/NPWP) | Y | N | N |
| Verify User Identity (KYC) | N | Y | Y |
| View All Users | N | Y | Y |
| Deactivate User Account | N | N | Y |

## 2. Project Location

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Create Project Location | Y | N | N |
| View Own Locations | Y (Own) | N | N |
| View All Locations | N | Y | Y |
| Update Own Location | Y (Own) | N | N |
| Delete Own Location | Y (Own) | N | N |

## 3. Equipment (Catalog & Inventory)

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Equipment Catalog (Models) | Y | Y | Y |
| View Equipment Availability | Y | Y | Y |
| Create Equipment Type | N | Y | Y |
| Create Equipment Model | N | Y | Y |
| Create Equipment Unit (Physical) | N | Y | Y |
| Update Equipment Unit Status | N | Y | Y |
| Decommission Equipment Unit | N | N | Y |
| View Equipment Unit Details (Serial/Plate) | N | Y | Y |

## 4. Pricing

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Public Pricing | Y | Y | Y |
| Calculate Price Estimate | Y | Y | Y |
| Create/Update Price Master | N | N | Y |
| View Price Version History | N | Y | Y |

## 5. Recommendation

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Request Recommendation | Y | Y | N |
| View Own Recommendation Results | Y (Own) | N | N |
| View All Recommendation Requests | N | Y | Y |

## 6. Cart

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Add Item to Cart | Y | N | N |
| View Own Cart | Y (Own) | N | N |
| Update Cart Item | Y (Own) | N | N |
| Remove Cart Item | Y (Own) | N | N |
| Clear Cart | Y (Own) | N | N |

## 7. Booking

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Create Booking (from Cart) | Y | N | N |
| View Own Bookings | Y (Own) | N | N |
| View All Bookings | N | Y | Y |
| View Booking Detail | Y (Own) | Y (All) | Y (All) |
| Submit Booking | Y (Own) | N | N |
| Approve Booking | N | Y | Y |
| Reject Booking | N | Y | Y |
| Cancel Booking (Pre-Payment) | Y (Own) | Y (All) | Y (All) |
| Cancel Booking (Post-Payment) | N | Y | Y |
| Reschedule Booking | Y (Own) | Y (All) | Y (All) |
| Assign Physical Units | N | Y | N |
| Replace Unit Assignment | N | Y | N |
| Extend Payment Deadline | N | Y | Y |
| Dispatch Units | N | Y | N |
| Confirm Arrival | N | Y | N |
| Validate BAST Check-in | N | Y | N |
| Validate BAST Check-out | N | Y | N |
| Mark Booking Completed | N | Y | N |

## 8. Rental Operations

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Own Rental Details | Y (Own) | N | N |
| View All Rental Operations | N | Y | Y |
| Dispatch Unit (Mobilize) | N | Y | N |
| Confirm Arrival at Site | N | Y | N |
| Record BAST Check-in | N | Y | N |
| Record BAST Check-out | N | Y | N |
| Initiate Demobilization | N | Y | N |
| Complete Return Inspection | N | Y | N |

## 9. Timesheet

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Create Timesheet Entry | Y | Y | N |
| View Own Timesheets | Y (Own) | N | N |
| View All Timesheets | N | Y | Y |
| Submit Timesheet for Approval | Y (Own) | Y (All) | N |
| Approve Timesheet | N | Y | N |
| Reject Timesheet | N | Y | N |
| Revise Timesheet (with History) | N | Y | N |
| View Timesheet Revision History | N | Y | Y |

## 10. Invoice

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Own Invoices | Y (Own) | N | N |
| View All Invoices | N | Y | Y |
| Download Invoice PDF | Y (Own) | Y (All) | Y (All) |
| Generate Invoice (System Triggered) | N | System | System |

## 11. Bank Account

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Active Bank Accounts | Y | Y | Y |
| Create Bank Account | N | N | Y |
| Update Bank Account | N | N | Y |
| Deactivate Bank Account | N | N | Y |

## 12. Payment

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Upload Payment Proof | Y (Own) | N | N |
| View Own Payments | Y (Own) | N | N |
| View All Payments | N | Y | Y |
| Approve Payment | N | Y | Y |
| Reject Payment | N | Y | Y |
| Flag Overpayment | N | Y | Y |

## 13. Refund

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Own Refund Status | Y (Own) | N | N |
| View All Refunds | N | Y | Y |
| Review Refund Request | N | Y | N |
| Approve Refund | N | N | Y |
| Reject Refund | N | N | Y |
| Process Refund (Upload Bank Proof) | N | Y | N |

## 14. Notification

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Own Notifications | Y (Own) | Y (Own) | Y (Own) |
| Mark as Read | Y (Own) | Y (Own) | Y (Own) |

## 15. Report & Dashboard

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Admin Dashboard | N | Y | Y |
| View Owner Dashboard | N | N | Y |
| View Revenue Report | N | N | Y |
| View Equipment Utilization Report | N | Y | Y |
| View Booking Summary Report | N | Y | Y |
| Export Report (CSV/PDF) | N | N | Y |

## 16. Audit & Activity Log

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Activity Logs | N | N | Y |
| Search Audit Trail | N | N | Y |

## 17. Business Calendar

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| View Calendar | Y | Y | Y |
| Manage Holiday/Working Day | N | Y | Y |

## 18. Attachment / Document

| Capability | USER | ADMIN | OWNER |
|---|---|---|---|
| Upload Attachment (Own Context) | Y | Y | N |
| View Attachment (Own Context) | Y (Own) | Y (All) | Y (All) |
| Delete Attachment | N | Y | Y |
