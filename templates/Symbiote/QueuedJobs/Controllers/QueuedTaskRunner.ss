$Header.RAW
$Info.RAW

<div class="task">
    <input type="radio" id="tab-all-tasks" class="task__selector task__selector--all" name="task__selector" checked="checked" />
    <label class="task__label task__label--all" for="tab-all-tasks">
        <span class="task__label-inner">All tasks</span>
    </label>

    <input type="radio" id="tab-universal-tasks" class="task__selector task__selector--universal" name="task__selector" />
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

    <div class="task__panel">
        <% if $Tasks.Count > 0 %>
            <div class="task__list">
                <% loop $Tasks %>
                    <div class="task__item task__item--{$Type}">
                        <div>
                            <h3 class="task__title">$Title</h3>
                            <div class="task__description">$Description</div>
                        </div>
                        <div>
                            <% if $TaskLink %>
                                <a href="{$TaskLink.ATT}" class="task__button">Run task</a>
                            <% end_if %>

                            <% if $QueueLink %>
                                <a href="{$QueueLink.ATT}" class="task__button">Queue job</a>
                            <% end_if %>
                        </div>
                    </div>
                <% end_loop %>
            </div>
        <% end_if %>
    </div>
</div>

$Footer.RAW
