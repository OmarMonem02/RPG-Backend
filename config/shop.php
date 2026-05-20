<?php

return [

    'name' => env('SHOP_NAME', 'Real Performance Garage'),

    'tagline' => env('SHOP_TAGLINE', 'Crafting the Ultimate Ride'),

    /*
    | Absolute URL or site-relative path (e.g. /logo.ico) shown on the public tracking page.
    */
    'logo_url' => env('SHOP_LOGO_URL', ''),

    /*
    | How often the customer dashboard auto-refreshes (minutes). Set to 0 to disable.
    */
    'tracking_auto_refresh_minutes' => (int) env('SHOP_TRACKING_AUTO_REFRESH_MINUTES', 2),

];
