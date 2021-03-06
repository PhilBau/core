<!DOCTYPE html>
<html lang="{$lang}" xml:lang="{$lang}">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset={$charset}" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="Zikula Aplication Framework">
        <meta name="author" content="Zikula Development Team">
        <title>{gt text="Zikula is already installed!"}</title>
        <link rel="stylesheet" href="web/bootstrap/css/bootstrap.min.css" type="text/css" />
        <link rel="stylesheet" href="web/bootstrap/css/bootstrap-theme.min.css" type="text/css" />
		<link rel="stylesheet" href="install/style/systemdialogs.css" type="text/css" />
    </head>
    <body>
        <div class="container">
            <div id="cell">
                <div id="content">
                    <h1>{gt text="Zikula Application Framework"}</h1>
                    <h2>{gt text='System is already installed!'}</h2>
                    <p>
                        {gt text='Zikula is already installed so the installer has been disabled.  If you need to run the installer a second time, you must reset config.php to its original state and clear the database tables before running the installer again. <a href="index.php">%s</a>.' tag1='Click here to visit your homepage'}
                        {gt text='Further information can be found in the <a href="http://community.zikula.org/Wiki-UserDocs.htm">%s</a>.' tag1='online documentation'}
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
