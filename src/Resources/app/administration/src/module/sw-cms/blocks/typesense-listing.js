const blockName = 'typesense-listing';

const component = {
    template: `
            <div :style="styles" class="sw-cms-block-${blockName} grid">
                <slot name="center"></slot>
            </div>
        `,

    computed: {
        styles() {
            return {
                gridTemplateColumns: '1fr',
            };
        },
    },
};

const preview = {
    template: `
            <div class="sw-cms-preview-block">
                <h4>{{ $tc('sw-cms.blocks.${blockName}.label') }}</h4>
            </div>
        `,
};

Shopware.Component.register(`sw-cms-block-preview-${blockName}`, () => preview);
Shopware.Component.register(`sw-cms-block-${blockName}`, () => component);
Shopware.Service('cmsService').registerCmsBlock({
    name: blockName,
    label: `sw-cms.blocks.${blockName}.label`,
    category: 'commerce',
    component: `sw-cms-block-${blockName}`,
    previewComponent: `sw-cms-block-preview-${blockName}`,
    defaultConfig: {
        marginBottom: '',
        marginTop: '',
        marginLeft: '',
        marginRight: '',
        sizingMode: 'boxed',
    },
    slots: {
        center: { type: 'typesense-listing' },
    },
});
