{{-- Safe readable fallback while external styles/fonts are unavailable. Never hide page content. --}}
<style id="landing-first-paint">
.landing{font-family:Inter,"Segoe UI",sans-serif;overflow-x:clip}
.landing img{max-width:100%;height:auto}
.landing .pi::before{visibility:hidden}
.landing.icons-ready .pi::before{visibility:visible}
:where(.landing .p-select){display:inline-flex;align-items:center}
:where(.landing .p-select-dropdown){display:flex;align-items:center;justify-content:center}
</style>
