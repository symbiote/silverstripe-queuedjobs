<div class="options">
	<% if $EchoMessage %>
		<h2>Job Echo Output:</h2>
		<div>
			$EchoMessage
		</div>
		<% if $Content %>
			<h2>Content:</h2>
		<% end_if %>
	<% end_if %>
	$Content
	<% if $Jobs.Count > 0 %>
		<ul>	
			<% loop $Jobs %>
			<li>
				<p>
					<a href="$Link">$Title</a> - <span>Pending in Queue: $Count</span><br/>
					<% if $Description %>
						<span class="description">
							$Description
						</span>
					<% end_if %>
				</p>
			</li>
			<% end_loop %>
		</ul>
	<% end_if %>
</div>
