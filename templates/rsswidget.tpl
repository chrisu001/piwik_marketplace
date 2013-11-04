{*
 @property array $rss - list of news to the appstore 
 @property boolean $error - flag, if an error occured 
 *}
{literal}
 <style>
 .rss ul {
    list-style:none outside none;
    padding:0;
}
.rss li {
    line-height:140%;
    margin:0.5em 0 1em;
}
.rss-title, .rss-date { 
    float:left;
    font-size:14px;
    line-height:140%;
}
.rss-title{
    color:#2583AD;
    margin:0 0.5em 0.2em 0;
    font-weight:bold;
}   
.rss-date {
    color:#999999;
    margin:0;
}
.rss-content, .rss-description {
    clear:both;
    line-height:1.5em;
    font-size:11px;
    color:#333333;
}
</style>
{/literal}
{if (empty($error)) }
<div style="padding: 10px 15px;">
	<ul class="rss">
		 {foreach from=$rss key=id item=entry}

		<li><a class="rss-title" title="" target="_blank"
			href="?module=Proxy&action=redirect&url={$entry.link|urlencode}">{$entry.title}</a>
			<span class="rss-date">{$entry.date}</span>
			<div class="rss-description">{$entry.description}</div>
{* 			<div class="rss-content">{$entry.content}</div>  *}
		</li> 
         {/foreach}
	</ul>
</div>
{else}
{'APUA_Feed_Widget_Error'|translate}
 <!--   {$error} -->
{/if}
