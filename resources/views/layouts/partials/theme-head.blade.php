{{-- Fuentes de OriA y tema claro u oscuro. El script va antes de los estilos para que la página no parpadee en claro. --}}
<script>
    (function () {
        try {
            var choice = localStorage.getItem('benditio.theme') || 'system';
            var dark = choice === 'dark' || (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        } catch (e) {
            document.documentElement.dataset.theme = 'light';
        }
    })();
</script>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=bricolage-grotesque:500,600,700,800|plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
