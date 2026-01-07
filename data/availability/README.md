# Availability Data Storage

This folder contains JSON files for storing health worker availability data.

Each health worker has their own JSON file named `{health_worker_id}.json`.

## File Structure

```json
{
    "unavailableDates": ["2026-01-10", "2026-01-15"],
    "slotLimits": {
        "2026-01-08": 10,
        "2026-01-09": 8
    },
    "defaultSlotLimit": 10,
    "lastUpdated": "2026-01-08T10:30:00"
}
```
