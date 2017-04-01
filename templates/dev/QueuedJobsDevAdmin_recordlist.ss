<div class="options">
	<% if $Records.Count > 0 %>
		<ul>	
			<% loop $Records %>
			<li>
				<p>
					<a href="$Link">$Title</a><br/>
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
