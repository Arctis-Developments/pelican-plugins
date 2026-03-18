@php
    use ArctisDev\ServerSplit\Filament\App\Resources\SplitServerResource;
@endphp

<template id="server-split-create-server-template">
    <div data-server-split-create-button-wrapper class="me-3 shrink-0">
        <a
            href="{{ SplitServerResource::getUrl('create') }}"
            data-server-split-create-button
            class="fi-btn fi-color-gray fi-size-sm inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium shadow-sm ring-1 ring-inset ring-gray-950/10 transition duration-75 hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5"
        >
            <x-filament::icon
                alias="server-split::create-server"
                icon="tabler-plus"
                class="h-5 w-5"
            />
            <span>{{ trans('server-split::server-split.app.navigation_label') }}</span>
        </a>
    </div>
</template>

@once
    <script>
        (() => {
            const templateId = 'server-split-create-server-template';
            const buttonWrapperSelector = '[data-server-split-create-button-wrapper]';
            const toolbarSelectors = [
                '.fi-ta-header-toolbar',
                '.fi-ta-toolbar',
                '.fi-ta-header-ctn .fi-ta-toolbar',
            ];

            const mountCreateServerButton = () => {
                const template = document.getElementById(templateId);

                if (!template) {
                    return;
                }

                const toolbar = toolbarSelectors
                    .map((selector) => document.querySelector(selector))
                    .find(Boolean);

                if (!toolbar) {
                    return;
                }

                const existingButton = toolbar.querySelector(buttonWrapperSelector);

                if (existingButton) {
                    if (toolbar.firstElementChild !== existingButton) {
                        toolbar.insertBefore(existingButton, toolbar.firstElementChild);
                    }

                    return;
                }

                const button = template.content.firstElementChild?.cloneNode(true);

                if (!button) {
                    return;
                }

                toolbar.insertBefore(button, toolbar.firstElementChild);
            };

            document.addEventListener('DOMContentLoaded', mountCreateServerButton);
            document.addEventListener('livewire:navigated', mountCreateServerButton);
            document.addEventListener('livewire:load', mountCreateServerButton);
            window.setTimeout(mountCreateServerButton, 250);
            window.setTimeout(mountCreateServerButton, 1000);

            const observer = new MutationObserver(() => mountCreateServerButton());

            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });

            window.setInterval(mountCreateServerButton, 3000);
        })();
    </script>
@endonce
