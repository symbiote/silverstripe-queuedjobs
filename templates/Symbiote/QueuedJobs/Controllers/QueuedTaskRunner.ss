$Header.RAW
$Info.RAW

<div class="task">
    <input type="radio" id="tab-universal-tasks" class="task__selector task__selector--universal" name="task__selector" checked="checked" />
    <label class="task__label task__label--universal" for="tab-universal-tasks">
        <span class="task__label-inner">Queueable task</span>
    </label>

    <input type="radio" id="tab-immediate-tasks" class="task__selector task__selector--immediate" name="task__selector" />
    <label class="task__label task__label--immediate" for="tab-immediate-tasks">
        <span class="task__label-inner">Non-queueable tasks</span>
    </label>

    <input type="radio" id="tab-queue-only-tasks" class="task__selector task__selector--queue-only" name="task__selector" />
    <label class="task__label task__label--queue-only" for="tab-queue-only-tasks">
        <span class="task__label-inner">Queueable only tasks</span>
    </label>

    <div class="task__panel task__panel--universal">
        <% if $UniversalTasks.Count > 0 %>
            <div class="task__list">
                <% loop $UniversalTasks %>
                    <div class="task__item">
                        <div>
                            <h3 class="task__title">$Title</h3>
                            <p class="task__description">$Description</p>
                        </div>
                        <div>
                            <a href="{$TaskLink.ATT}" class="task__button">Run task</a>
                            <a href="{$QueueLink.ATT}" class="task__button">Queue job</a>
                        </div>
                    </div>
                <% end_loop %>
            </div>
        <% end_if %>
    </div>

    <div class="task__panel task__panel--immediate">
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
