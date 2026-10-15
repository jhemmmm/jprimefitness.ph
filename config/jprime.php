<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature toggles
    |--------------------------------------------------------------------------
    |
    | cash_drawer: enables the Cash Drawer page, its routes, and the automatic
    | ledger entries recorded from cash sales and cash payouts. Disable with
    | CASH_DRAWER=false in .env.
    |
    | attendance_auto_checkout_hours: members/walk-ins still checked in after
    | this many hours are checked out automatically (hourly schedule). Walk-ins
    | have no kiosk time-out and members often forget theirs.
    |
    | employee_max_shift_hours: a fingerprint tap more than this many hours after
    | an employee's open time-in starts a new time-in instead of closing the old
    | one (a forgotten time-out). Must exceed the longest real shift.
    |
    | hikvision_repeat_tap_seconds: taps this soon after the previous one are
    | ignored, so a double tap doesn't check the employee straight back out.
    |
    */

    'cash_drawer' => (bool) env('CASH_DRAWER', true),

    'attendance_auto_checkout_hours' => (int) env('ATTENDANCE_AUTO_CHECKOUT_HOURS', 4),

    'employee_max_shift_hours' => (int) env('EMPLOYEE_MAX_SHIFT_HOURS', 16),

    'hikvision_repeat_tap_seconds' => (int) env('HIKVISION_REPEAT_TAP_SECONDS', 120),

];
