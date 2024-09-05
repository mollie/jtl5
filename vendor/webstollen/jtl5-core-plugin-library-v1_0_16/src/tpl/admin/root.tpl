<script>
    // <!--
    document.getElementById('content_wrapper').setAttribute('style', 'position:relative');
    const addCss = function (url) {
        const cssId = btoa(url).substr(btoa(url).length - 32, 32);  // you could encode the css path itself to generate id..
        if (!document.getElementById(cssId)) {
            const head = document.getElementsByTagName('head')[0];
            const link = document.createElement('link');
            link.id = cssId;
            link.rel = 'stylesheet';
            link.type = 'text/css';
            link.href = url;
            link.media = 'all';
            head.appendChild(link);
        }
    };
    {foreach from=$css item=file}
    addCss('{$file}');
    {/foreach}
    // -->
</script>
{$body}