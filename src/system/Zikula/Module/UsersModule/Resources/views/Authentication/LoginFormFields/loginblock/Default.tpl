<div class="form-group">
    <label class="control-label sr-only" for="users_loginblock_login_id">
        {if $authentication_method == 'email'}
            {gt text='Email address'}
        {elseif $authentication_method == 'uname'}
            {gt text='User name'}
        {elseif $authentication_method == 'unameoremail'}
            {gt text='User name or e-mail address'}
        {/if}
    </label>
    <input id="users_loginblock_login_id" class="form-control input-sm" type="text" name="authentication_info[login_id]" maxlength="64" value="" placeholder="{if $authentication_method == 'email'}{gt text='Email address'}{elseif $authentication_method == 'uname'}{gt text='User name'}{elseif $authentication_method == 'unameoremail'}{gt text='User name or e-mail address'}{/if}" />
</div>

<div class="form-group">
    <label class="control-label sr-only" for="users_loginblock_pass">{gt text="Password"}</label>
    <input id="users_loginblock_pass" class="form-control input-sm" type="password" name="authentication_info[pass]" maxlength="25" placeholder="{gt text="Password"}" />
</div>