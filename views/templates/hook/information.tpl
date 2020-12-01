<div class="tab">
  <button class="tablinks" onclick="changeTab(event, 'Information')" id="defaultOpen">{l s='Information' mod='plisio'}</button>
  <button class="tablinks" onclick="changeTab(event, 'Configure Settings')">{l s='Configure Settings' mod='plisio'}</button>
</div>

<!-- Tab content -->
<div id="Information" class="tabcontent">
	<div class="wrapper">
	  <h2 class="plisio-information-header">
      {l s='Accept Bitcoin, Litecoin, Ethereum and other digital currencies on your PrestaShop store with Plisio' mod='plisio'}
    </h2><br/>
	  <strong>{l s='What is Plisio?' mod='plisio'}</strong> <br/>
	  <p>
      {l s='We offer a fully automated cryptocurrency processing platform and invoice system.' mod='plisio'}
    </p><br/>
	  <strong>{l s='Getting started' mod='plisio'}</strong><br/>
	  <p>
	  	<ul>
	  		<li>{l s='Install the Plisio module on PrestaShop' mod='plisio'}</li>
	  		<li>
          {l s='Visit ' mod='plisio'}<a href="https://plisio.net" target="_blank">{l s='plisio.net' mod='plisio'}</a>
          {l s='and create an account' mod='plisio'}
         </li>
	  		<li>{l s='Get your API credentials and copy-paste them to the Configuration page in Plisio module' mod='plisio'}</li>
	  	</ul>
	  </p>
	  <p class="sign-up"><br/>
	  	<a href="https://plisio.net/account/signup" class="sign-up-button">{l s='Sign up on Plisio' mod='plisio'}</a>
	  </p><br/>
	  <strong>{l s='Features' mod='plisio'}</strong>
	  <p>
	  	<ul>
	  		<li>{l s='The gateway is fully automatic - set and forget it.' mod='plisio'}</li>
	  		<li>{l s='Payment amount is calculated using real-time exchange rates' mod='plisio'}</li>
	  	</ul>
	  </p>

	  <p><i>{l s='Questions? Contact plugins@plisio.net !' mod='plisio'}</i></p>
	</div>
</div>

<div id="Configure Settings" class="tabcontent">
  {html_entity_decode($form|escape:'htmlall':'UTF-8')}
</div>

<script>
	document.getElementById("defaultOpen").click();
</script>
