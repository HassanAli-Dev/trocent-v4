<x-filament-panels::page>
    {{ $this->form }}
    {{-- @livewire('floating-order-cart') --}}
{{--    <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />--}}
</x-filament-panels::page>

@push('styles')
    <style>
        /* Scope CSS to only the service charges section */
        .service-charges-section .fi-fo-repeater-item {
            display: flex !important;
            border: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .service-charges-section .fi-fo-repeater-item-header {
            order: 2 !important;
            padding: 0.25rem !important;
        }

        .service-charges-section .fi-fo-repeater-item-content {
            padding: 0.25rem !important;
            flex: 1 !important;
            border: 0 !important;
        }

        /* Remove border from the grid container inside repeater - scoped */
        .service-charges-section .fi-fo-repeater-item-content .grid {
            border: 0 !important;
            box-shadow: none !important;
        }

        /* Target the specific grid class - scoped */
        .service-charges-section .grid.grid-cols-\[--cols-default\] {
            border: 0 !important;
            box-shadow: none !important;
        }

        /* More specific targeting for the grid with gap - scoped */
        .service-charges-section .fi-fo-repeater-item .grid.grid-cols-\[--cols-default\].items-start.gap-4 {
            border: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        /* Remove any remaining borders from child elements - scoped */
        .service-charges-section .fi-fo-repeater-item .grid > * {
            border: 0 !important;
        }

        /* Alternative: Use ID selector for even more specificity */
        #additional-service-charges .fi-fo-repeater-item {
            display: flex !important;
            border: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        #additional-service-charges .fi-fo-repeater-item-header {
            order: 2 !important;
            padding: 0.25rem !important;
        }

        #additional-service-charges .fi-fo-repeater-item-content {
            padding: 0.25rem !important;
            flex: 1 !important;
            border: 0 !important;
        }

        #additional-service-charges .fi-fo-repeater-item-content .grid {
            border: 0 !important;
            box-shadow: none !important;
        }

        #additional-service-charges .grid.grid-cols-\[--cols-default\] {
            border: 0 !important;
            box-shadow: none !important;
        }

        #additional-service-charges .fi-fo-repeater-item .grid.grid-cols-\[--cols-default\].items-start.gap-4 {
            border: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        #additional-service-charges .fi-fo-repeater-item .grid > * {
            border: 0 !important;
        }


    </style>
@endpush
