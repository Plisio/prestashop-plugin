<div class="row">
    <div class="col-xs-12">
        <p class="payment_module">
            <a class="cheque" style="background-image: url('{$this_path|escape:'htmlall':'UTF-8'}views/img/bitcoin-logo.png'); padding-left:150px;  background-size: 100px; background-position: 20px; 50%; background-repeat: no-repeat;" href="{$link->getModuleLink('plisio', 'payment')|escape:'htmlall':'UTF-8'}">
                {l s='Bitcoin, Ethereum, Litecoin or other (via Plisio)' mod='plisio'}
                <br><span>({l s='order processing will be faster' mod='plisio'})</span>
            </a>
        </p>
    </div>
</div>
