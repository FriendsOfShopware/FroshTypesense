window.PluginManager.register(
    'TypesenseListing',
    () => import('./plugin/typesense/typesense-listing.plugin'),
    '[data-typesense-listing]'
);
window.PluginManager.register(
    'TypesenseSuggest',
    () => import('./plugin/typesense/typesense-suggest.plugin'),
    '[data-typesense-suggest]'
);
