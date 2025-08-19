<div>
    {{-- Floating Cart Widget - Pure inline styles for guaranteed display --}}
    <div 
        wire:click="mountAction('viewDetails')"
        style="position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: linear-gradient(135deg, #f5a100 0%, #e6920a 100%); color: white; padding: 16px 20px; border-radius: 16px; box-shadow: 0 10px 25px rgba(245, 161, 0, 0.3); cursor: pointer; transition: all 0.3s ease; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-width: 220px; border: 2px solid rgba(255, 255, 255, 0.1);"
        onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 15px 35px rgba(245, 161, 0, 0.4)'"
        onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 10px 25px rgba(245, 161, 0, 0.3)'"
    >
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <svg style="width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2;" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.293 1.293a1 1 0 00-.707.707V19a2 2 0 002 2h7a2 2 0 002-2v-4a1 1 0 00-.293-.707L15 13H7z"></path>
                </svg>
                <span style="font-size: 14px; font-weight: 500;">Order Total</span>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 20px; font-weight: bold; line-height: 1.2;">${{ number_format($total, 2) }}</div>

            </div>
        </div>
        
        {{-- Pulse indicator --}}
        <div style="position: absolute; top: 8px; right: 8px; width: 8px; height: 8px; background: rgba(255, 255, 255, 0.8); border-radius: 50%; animation: pulse 2s infinite;"></div>
    </div>

    {{-- Add pulse animation --}}
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
    </style>

    {{-- Alternative Compact Version --}}
    {{-- 
    <button 
        wire:click="mountAction('viewDetails')"
        style="position: fixed; bottom: 24px; right: 24px; z-index: 9999; background: #f5a100; color: white; padding: 14px 18px; border-radius: 50px; box-shadow: 0 8px 20px rgba(245, 161, 0, 0.4); cursor: pointer; transition: all 0.3s ease; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-weight: 600; border: none; font-size: 16px;"
        onmouseover="this.style.transform='scale(1.1)'; this.style.background='#e6920a'"
        onmouseout="this.style.transform='scale(1)'; this.style.background='#f5a100'"
    >
        💰 ${{ number_format($total, 2) }}
    </button>
    --}}

    {{-- Filament Actions Modals --}}
    <x-filament-actions::modals />
</div>