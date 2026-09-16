{{-- The one raised surface in the panel. Everything inside it uses hairlines. --}}
<div {{ $attributes->class('rounded-card border border-hairline bg-surface shadow-card') }}>
    {{ $slot }}
</div>
