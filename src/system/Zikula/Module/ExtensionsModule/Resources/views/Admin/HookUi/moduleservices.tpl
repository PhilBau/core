{adminheader}
<h3>
    <span class="fa fa-paperclip"></span>
    {gt text='Module Services'}
</h3>

<p class="alert alert-info">{gt text='Module Services are functions provided by the core or other modules for this module.'}</p>

{if count($sublinks) > 0}
<ul style='list-style: none'>
    {foreach from=$sublinks item='sublink'}
    <li><a href='{$sublink.url|safetext}' class='fa fa-cog'>{$sublink.text|safetext}</a></li>
    {/foreach}
</ul>
{else}
<p>{gt text="There aren't any modules services available."}</p>
{/if}
{adminfooter}