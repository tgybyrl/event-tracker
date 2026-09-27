@props(['action'])

@php
    // Value -> badge tone for event_action. Lives here rather than in each
    // view because two screens show actions (the events list and the
    // dashboard), and the same action must read as the same colour on both.
    // Anything unmapped falls back to slate inside <x-badge>, so a new action
    // from Go shows up grey rather than breaking.
    $tones = [
        'product_click' => 'blue',
        'add_to_cart' => 'amber',
        'checkout_start' => 'green',
        // Sent by the market on every page it draws.
        'page_view' => 'violet',
    ];
@endphp

<x-badge :tone="$tones[$action] ?? 'slate'" {{ $attributes }}>{{ $action }}</x-badge>
