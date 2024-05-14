import instantsearch, {Expand, UiState} from 'instantsearch.js/es';
import {highlight} from 'instantsearch.js/es/helpers';
import {
    searchBox,
    pagination,
    refinementList,
    hits,
    stats,
    sortBy,
    hierarchicalMenu,
    rangeSlider,
    toggleRefinement,
    hitsPerPage,
    ratingMenu,
    clearRefinements
} from 'instantsearch.js/es/widgets';
import TypesenseInstantSearchAdapter from 'typesense-instantsearch-adapter';
import {InstantSearch} from "instantsearch.js";
import type {Hit} from "instantsearch.js/es/types";

export default class TypesenseListingPlugin extends window.PluginBaseClass {

    private search: InstantSearch;
    private options: {
        boxLayout: string;
        isoCode: string;
        translations: { boxAddProduct: null; boxProductDetails: null };
        addToCartRoute: null;
        allowBuyInListing: boolean;
        filters: any[];
        propertyGroups: any[];
        locale: string,
        priceField: string,
    };

    static options = {
        boxLayout: 'standard',
        allowBuyInListing: true,
        addToCartRoute: null,
        locale: 'en-GB',
        isoCode: 'GBP',
        translations: {
            boxAddProduct: null,
            boxProductDetails: null,
        },
        filters: [],
        propertyGroups: [],
        priceField: 'price',
    }


    init() {
        const {
            indexName,
            salesChannelId,
            apiKey,
            nodes,
            boxLayout,
            priceField,
        } = this.options;

        const typesenseInstantsearchAdapter = new TypesenseInstantSearchAdapter(
            {
                server: {
                    apiKey: apiKey,
                    nodes: nodes,
                },
                additionalSearchParameters: {
                    queryBy: 'name,description',
                    prefix: true,
                    group_by: 'displayGroup',
                    filter_by: `salesChannelIds:[${salesChannelId}]`,
                }
            }
        );

        const searchClient = typesenseInstantsearchAdapter.searchClient;
        this.search = instantsearch({
            searchClient,
            indexName: indexName,
            routing: true,
        });

        this.search.addWidgets([
            searchBox({
                container: '#searchbox',
                showSubmit: false,
                showReset: false,
                placeholder: 'Search for products... ',
                autofocus: false,
                cssClasses: {
                    input: 'form-control',
                    loadingIcon: 'stroke-primary',
                },
            }),
            pagination({
                container: '#pagination',
                cssClasses: {
                    list: 'd-flex flex-row justify-content-end',
                    item: 'px-2 d-block',
                    link: 'text-decoration-none',
                    disabledItem: 'text-muted',
                    selectedItem: 'fw-bold text-primary',
                },
            }),
            sortBy({
                container: '#sort-by',
                items: [
                    {label: 'Relevancy', value: indexName},
                    {label: 'Price (asc)', value: `${indexName}/sort/${priceField}:asc`},
                    {label: 'Price (desc)', value: `${indexName}/sort/${priceField}:desc`},
                ],
                cssClasses: {
                    select: 'form-select',
                },
            }),
            hits({
                container: '#hits',
                templates: {
                    item: (hit) => this.itemTemplate(hit),
                },
                cssClasses: {
                    list: 'list-unstyled',
                    item: `card product-box box-${boxLayout}`,
                    loadMore: 'btn btn-primary mx-auto mt-4',
                    disabledLoadMore: 'btn btn-dark mx-auto mt-4',
                },
            }),
            hitsPerPage({
                container: '#hits-per-page',
                items: [
                    {label: '9 per page', value: 9, default: true},
                    {label: '18 per page', value: 18},
                ],
                cssClasses: {
                    select: 'form-select',
                },
            }),
            stats({
                container: '#stats',
                templates: {
                    text: `
                      {{#hasNoResults}}No products{{/hasNoResults}}
                      {{#hasOneResult}}1 product{{/hasOneResult}}
                      {{#hasManyResults}}{{#helpers.formatNumber}}{{nbHits}}{{/helpers.formatNumber}} products{{/hasManyResults}}
                      found in {{processingTimeMS}}ms
                    `,
                },
                cssClasses: {
                    text: 'small',
                },
            }),
            clearRefinements({
                container: '#clear-refinements',
                cssClasses: {
                    button: 'btn btn-light',
                },
            }),
        ]);

        //this.categoryFilter();
        this.manufacturerFilter();
        this.ratingFilter();
        this.priceFilter();
        this.shippingFreeFilter();
        this.propertyGroupsFilter();

        this.search.on('render', () => {
            window.PluginManager.initializePlugins();
        });

        this.search.start();
    }

    categoryFilter() {
        if (!this.options.filters.includes('category-filter')) {
            return;
        }

        this.search.addWidgets([
            hierarchicalMenu({
                container: '#categories-hierarchical-menu',
                showParentLevel: true,
                rootPath: 'Cell Phones',
                attributes: [
                    'categories.lvl0',
                    'categories.lvl1',
                    'categories.lvl2',
                    'categories.lvl3',
                ],
                cssClasses: {
                    showMore: 'btn btn-secondary btn-sm',
                    list: 'list-unstyled',
                    childList: 'ms-4',
                    count: 'badge text-bg-dark ms-2',
                    link: 'text-dark text-decoration-none',
                    selectedItem: 'text-primary fw-bold',
                    parentItem: 'text-dark fw-bold',
                },
            }),
        ]);
    }

    manufacturerFilter() {
        if (!this.options.filters.includes('manufacturer-filter')) {
            return;
        }

        this.search.addWidgets([
            refinementList({
                limit: 10,
                showMoreLimit: 50,
                container: '#brand-list',
                attribute: 'manufacturerName',
                searchable: true,
                searchablePlaceholder: 'Search brands',
                showMore: true,
                sortBy: ['name:asc', 'count:desc'],
                cssClasses: {
                    searchableInput: 'form-control form-control-sm mb-2',
                    searchableSubmit: 'd-none',
                    searchableReset: 'd-none',
                    showMore: 'btn btn-light',
                    list: 'list-unstyled',
                    count: 'badge text-bg-dark ms-2',
                    label: 'd-flex align-items-center',
                    checkbox: 'me-2',
                },
            })
        ]);
    }

    ratingFilter() {
        if (!this.options.filters.includes('rating-filter')) {
            return;
        }

        this.search.addWidgets([
            ratingMenu({
                container: '#rating-menu',
                attribute: 'ratingAverage',
                cssClasses: {
                    list: 'list-unstyled',
                    link: 'text-decoration-none',
                    starIcon: '',
                    count: 'badge text-bg-dark ms-2',
                    disabledItem: 'text-muted',
                    selectedItem: 'fw-bold text-primary',
                },
            })
        ]);
    }

    priceFilter() {
        if (!this.options.filters.includes('price-filter')) {
            return;
        }

        this.search.addWidgets([
            rangeSlider({
                container: '#price-range-slider',
                attribute: this.options.priceField,
            }),
        ]);
    }

    shippingFreeFilter() {
        if (!this.options.filters.includes('shipping-free-filter')) {
            return;
        }

        this.search.addWidgets([
            toggleRefinement({
                container: '#shipping-free-filter',
                attribute: 'shippingFree',
                templates: {
                    labelText: 'Shipping free',
                },
                cssClasses: {
                    label: 'd-flex align-items-center',
                    checkbox: 'me-2',
                },
            }),
        ]);
    }

    propertyGroupsFilter() {
        Object.values(this.options.propertyGroups).map((group) => {
            this.search.addWidgets([
                refinementList({
                    limit: 10,
                    showMoreLimit: 50,
                    container: `#property-group-${group.id}`,
                    attribute: 'property_' + group.id,
                    searchable: true,
                    searchablePlaceholder: `Search ${group.name}`,
                    showMore: true,
                    sortBy: ['name:asc'],
                    cssClasses: {
                        searchableInput: 'form-control form-control-sm mb-2',
                        searchableSubmit: 'd-none',
                        searchableReset: 'd-none',
                        showMore: 'btn btn-light',
                        list: 'list-unstyled',
                        count: 'badge text-bg-dark ms-2',
                        label: 'd-flex align-items-center',
                        checkbox: 'me-2',
                    },
                })
            ]);
        });
    }

    itemTemplate(hit: Hit) {
        const {
            id,
            name,
            url,
            image,
            imageSrcSet,
            imageWidth,
            imageHeight,
            ratingAverage,
            isCloseout,
            stock,
            childCount,
            minPurchase
        } = hit;

        const isAvailable = !isCloseout || (stock >= minPurchase);
        const displayBuyButton = isAvailable && childCount <= 0;
        const price = this.options.priceField;

        const formattedPrice = new Intl.NumberFormat(
            this.options.locale, {
                style: 'currency',
                currency: this.options.isoCode,
            },
        ).format(hit[price]);

        return `
            <div class="card-body" data-product-information='{"id": "${id}", "name":"${name}"}'>
                <div class="product-image-wrapper">
                    <a href="${url}" title="${name}" class="product-image-link is-cover">
                        <img class="product-image is-cover"
                                src="${image}" srcSet="${imageSrcSet}" alt="${name}"
                                loading="lazy"
                                width="${imageWidth}" height="${imageHeight}" />
                    </a>
                </div>
                <div class="product-name">
                    ${highlight({attribute: 'name', hit})}
                </div>

                ${ratingAverage ? `
                    <div class="product-rating">
                       ${ratingAverage}/5
                    </div>
                ` : ''}

                <div class="product-description">
                    ${highlight({attribute: 'description', hit})}
                </div>

                <div class="product-price-info">
                  ${formattedPrice}
                </div>

                <div class="product-action">
                    <form action="${this.options.addToCartRoute}" method="post" class="buy-widget" data-add-to-cart="true">
                        <input type="hidden" name="redirectTo" value="frontend.detail.page">
                        <input type="hidden" name="redirectParameters" data-redirect-parameters="true" value='{"productId": "${id}"}'>
                        <input type="hidden" name="lineItems[${id}][id]" value="${id}">
                        <input type="hidden" name="lineItems[${id}][referencedId]" value="${id}">
                        <input type="hidden" name="lineItems[${id}][type]" value="product">
                        <input type="hidden" name="lineItems[${id}][stackable]" value="1">
                        <input type="hidden" name="lineItems[${id}][removable]" value="1">
                        <input type="hidden" name="lineItems[${id}][quantity]" value="${minPurchase}">
                        <input type="hidden" name="product-name" value="${name}">

                        <div class="d-grid">
                            ${this.options.allowBuyInListing && displayBuyButton ? `
                                <button class="btn btn-buy" title="${this.options.translations.boxAddProduct}">
                                    ${this.options.translations.boxAddProduct}
                                </button>
                                ` : `
                                <a href="${url}" class="btn btn-light btn-detail" title="${this.options.translations.boxProductDetails}">
                                    ${this.options.translations.boxProductDetails}
                                </a>
                            `}
                        </div>
                    </form>
                </div>
            </div>
      `;
    }
}
