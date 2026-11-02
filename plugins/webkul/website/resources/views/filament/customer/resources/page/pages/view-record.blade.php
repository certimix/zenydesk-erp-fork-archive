<x-filament-panels::page>
    @push('styles')
        <meta name="description" content="{{ trim($record->meta_description) != '' ? $record->meta_description : \Illuminate\Support\Str::limit(strip_tags($record->content), 120, '') }}"/>
        <meta name="keywords" content="{{ $record->meta_keywords }}"/>
        <meta name="twitter:title" content="{{ $record->title }}" />
        <meta name="twitter:description" content="{!! htmlspecialchars(trim(strip_tags($record->content))) !!}" />
        <meta property="og:type" content="article" />
        <meta property="og:title" content="{{ $record->title }}" />
        <meta property="og:description" content="{!! htmlspecialchars(trim(strip_tags($record->content))) !!}" />
        <meta property="og:url" content="{{ self::getResource()::getUrl('view', ['record' => $record->slug]) }}" />
    @endPush

    <div style="max-width: 980px; margin: 0 auto; padding: 32px 16px 64px 16px;">
        <div style="background-color: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05); padding: 48px 36px;">
            <div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="fi-prose {{ app()->getLocale() === 'ar' ? 'text-right' : 'text-left' }}" style="font-family: {{ app()->getLocale() === 'ar' ? 'Cairo, sans-serif' : 'inherit' }}; color: #334155; line-height: 1.75; font-size: 15px;">
                {!! $record->content !!}
            </div>
        </div>
    </div>
</x-filament-panels::page>
