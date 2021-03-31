{if $redirect != ''}
  {row}
    {col md=4 lg=3 xl=2}
      {button block=true type="link" href={$redirect} variant="primary"}
        {lang key='payNow' section='global'}
      {/button}
    {/col}
  {/row}
  {if $checkoutMode == 'D'}
    <meta http-equiv="refresh" content="{$smarty.const.MOLLIE_REDIRECT_DELAY}; URL={$redirect}">
  {/if}
{/if}

