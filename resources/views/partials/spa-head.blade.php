<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">

<title>{{ config('app.name') }}</title>

{{-- Apply the saved language, direction and theme before first paint to avoid a flash. --}}
<script>
    (function () {
        var root = document.documentElement;
        var locale = null;
        var theme = null;

        try {
            locale = localStorage.getItem('locale');
            theme = localStorage.getItem('theme');
        } catch (e) {}

        if (locale === 'fa' || locale === 'en') {
            root.lang = locale;
            root.dir = locale === 'fa' ? 'rtl' : 'ltr';
        }

        if (theme === 'dark' || (theme === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            root.classList.add('dark');
        }
    })();
</script>
