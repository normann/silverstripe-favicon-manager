<% with $SiteConfig %>
    <% cached $FaviconsCacheKey %>
        <% if $Favicon96x96 %><% with $Favicon96x96 %>
            <link rel="icon" type="image/png" href="{$Link}?v={$ID}_{$Version}" sizes="96x96" />
        <% end_with %><% end_if %>
        <% if $FaviconSVG %><% with $FaviconSVG %>
            <link rel="icon" type="image/svg+xml" href="{$Link}?v={$ID}_{$Version}" />
        <% end_with %><% end_if %>
        <% if $Favicon %><% with $Favicon %>
            <link rel="shortcut icon" href="{$Link}?v={$ID}_{$Version}" />
        <% end_with %><% end_if %>
        <% if $AppleTouchIcon %><% with $AppleTouchIcon %>
            <link rel="apple-touch-icon" sizes="180x180" href="{$Link}?v={$ID}_{$Version}" />
        <% end_with %><% end_if %>
        <meta name="apple-mobile-web-app-title" content="{$Title.ATT}" />
        <% if $Manifest %><% with $Manifest %>
            <link rel="manifest" href="{$Link}?v={$ID}_{$Version}" />
        <% end_with %><% end_if %>
    <% end_cached %>
<% end_with %>
