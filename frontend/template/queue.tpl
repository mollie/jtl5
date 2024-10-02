<script type="text/javascript">
{if isset($wsQueueURL)}{literal}
    $(function() {
        fetch({/literal}'{$wsQueueURL}{literal}', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'omit',
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json(); // If the response is JSON
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });
{/literal}{/if}
</script>