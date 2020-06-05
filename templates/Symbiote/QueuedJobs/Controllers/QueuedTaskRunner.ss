$Header.RAW
$Info.RAW

<div class="task">
    <label class="task__label" for="tab-universal-tasks">Queueable task</label>
    <input type="radio" id="tab-universal-tasks" class="task__selector task__selector--universal" name="task__selector" checked="checked" />
    <label class="task__label" for="tab-immediate-tasks">Non-queueable tasks</label>
    <input type="radio" id="tab-immediate-tasks" class="task__selector task__selector--immediate" name="task__selector" />
    <label class="task__label" for="tab-queue-only-tasks">Queueable only tasks</label>
    <input type="radio" id="tab-queue-only-tasks" class="task__selector task__selector--queue-only" name="task__selector" />

    <div class="task__panel task__panel--universal">
        <h2>Queueable tasks</h2>
        <p>By default these jobs will be added the job queue, rather than run immediately.</p>
        <% if $UniversalTasks.Count > 0 %>
            <div class="task__list">
                <% loop $UniversalTasks %>
                    <div class="task__item">
                        <div>
                            <h3>$Title</h3>
                            <p class="description">$Description</p>
                        </div>
                        <div>
                            <a href="{$TaskLink.ATT}" class="task__button task__button--warning">Run immediately</a>
                            <a href="{$QueueLink.ATT}" class="task__button task__button--notice">Add to queue</a>
                        </div>
                    </div>
                <% end_loop %>
            </div>
        <% end_if %>
    </div>

    <div class="task__panel task__panel--immediate">
        <h2>Non-queueable tasks</h2>
        <p>These tasks shouldn't be added the queuejobs queue, but you can run them immediately.</p>
        <% if $ImmediateTasks.Count > 0 %>
            <div class="task__list">
                <% loop $ImmediateTasks %>
                    <div class="task__item">
                        <div>
                            <h3>$Title</h3>
                            <p class="description">$Description</p>
                        </div>
                        <div>
                            <a href="{$TaskLink.ATT}" class="task__button task__button--warning">Run immediately</a>
                        </div>
                    </div>
                <% end_loop %>
            </div>
        <% end_if %>
    </div>

    <div class="task__panel task__panel--queue-only">
        <h2>Queueable only tasks</h2>
        <p>These tasks must be be added the queuejobs queue, running it immediately is not allowed.</p>
        <% if $QueueOnlyTasks.Count > 0 %>
            <div class="task__list">
                <% loop $QueueOnlyTasks %>
                    <div class="task__item">
                        <div>
                            <h3>$Title</h3>
                            <p class="description">$Description</p>
                        </div>
                        <div>
                            <a href="{$QueueLink.ATT}" class="task__button task__button--notice">Add to queue</a>
                        </div>
                    </div>
                <% end_loop %>
            </div>
        <% end_if %>
    </div>
</div>

$Footer.RAW
