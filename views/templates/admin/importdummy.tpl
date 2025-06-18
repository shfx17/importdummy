{if isset($message)}
    {$message nofilter}
{/if}

<h4> Import products from Dummy with our plugin. The plugin is free of charge. </h4> <br />

<form method="post" action="{$import_url}">
    <button type="submit" name="import_dummy_products" class="btn btn-primary">
        Import
    </button>
</form>

