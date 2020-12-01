{if $state == $paid_state}
        <p>{l s='Your order on %s is complete.' mod='plisio'}
                <br /><br /> <strong>{l s='Your order will be sent as soon as your payment is confirmed.' mod='plisio'}</strong>
                <br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='plisio'} <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support team. ' mod='plisio'}</a>
        </p>
{else}
      	<p class="warning">
                {l s='We noticed a problem with your order. If you think this is an error, feel free to contact our' mod='plisio'}
                <a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer support team. ' mod='plisio'}</a>.
        </p>
{/if}
