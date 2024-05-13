const NAME = 'typesense-listing';

Shopware.Component.extend(`sw-cms-el-component-${NAME}`, 'sw-cms-el-product-listing',() => import('./component'));
Shopware.Component.extend(`sw-cms-el-preview-${NAME}`, 'sw-cms-el-preview-product-listing',() => import('./preview'));
Shopware.Component.extend(`sw-cms-el-config-${NAME}`, 'sw-cms-el-config-product-listing', () => import('./config'));
const productListing = Shopware.Service('cmsService').getCmsElementConfigByName('product-listing');
const typesenseListing = JSON.parse(JSON.stringify(productListing));

typesenseListing.name = NAME;
typesenseListing.label = `sw-cms.elements.${NAME}.label`;
typesenseListing.component = `sw-cms-el-component-${NAME}`;
typesenseListing.configComponent = `sw-cms-el-config-${NAME}`;
typesenseListing.previewComponent = `sw-cms-el-preview-${NAME}`;
typesenseListing.hidden = false;
typesenseListing.removable = true;

Shopware.Service('cmsService').registerCmsElement(typesenseListing);
