<h2><% _t('QUEUED_JOBS', 'Queued Jobs') %></h2>

<div id="treepanes">
	<div id="sitetree_holder">
		<ul id="sitetree" class="tree unformatted">
			<li id="$ID" class="Root"><a><strong><% _t('QUEUED_JOBS', 'Queued Jobs') %></strong></a>
				<ul>
					<li id="0">
						<a href="{$BaseHref}admin/queuedjobs/showqueue/0" title="">All</a>
					</li>
					<li id="1">
						<a href="{$BaseHref}admin/queuedjobs/showqueue/1" title="">Immediate</a>
					</li>
					<li id="2">
						<a href="{$BaseHref}admin/queuedjobs/showqueue/2" title="">Queued</a>
					</li>
					<li id="3">
						<a href="{$BaseHref}admin/queuedjobs/showqueue/3" title="">Large</a>
					</li>
					<!-- all other users' changes-->
				</ul>
			</li>
		</ul>
	</div>
</div>