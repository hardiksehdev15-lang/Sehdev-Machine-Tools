/* ============================================================
   SMT — GLOBAL PRODUCT SEARCH
   Automatically reads product names from all product pages.
   ============================================================ */


/* ============================================================
   PRODUCT PAGES
   ============================================================ */

const productPages = [
    "products/ancillary-units.html",
    "products/change-parts.html",
    "products/conveyor-system.html",
    "products/filling-machine.html",
    "products/labelling-machine.html",
    "products/mechanical-seals.html",
    "products/miscellaneous.html",
    "products/nozzles-needles.html",
    "products/rubber-components.html",
    "products/sealing-machine.html",
    "products/washing-units.html"
];


/* ============================================================
   SITE ROOT
   ============================================================ */

const siteRoot = new URL("../", document.currentScript.src);


/* ============================================================
   GLOBAL SEARCH INDEX
   ============================================================ */

let productSearchIndex = [];


/* ============================================================
   BUILD PRODUCT SEARCH INDEX
   ============================================================ */

async function buildProductSearchIndex() {

    const products = [];

    for (const page of productPages) {

        try {

            /* Load product page */

            const response = await fetch(
                new URL(page, siteRoot)
            );

            if (!response.ok) {

                console.warn(
                    "Could not load product page:",
                    page
                );

                continue;
            }


            /* Read HTML */

            const html = await response.text();


            /* Parse HTML */

            const parser = new DOMParser();

            const doc = parser.parseFromString(
                html,
                "text/html"
            );


            /* Find all searchable products */

            const cards = doc.querySelectorAll(
                ".searchable-product"
            );


            /* Read product names */

            cards.forEach(function (card) {

                const titleElement = card.querySelector("h3");

                if (!titleElement) {
                    return;
                }


                const name = titleElement.textContent
                    .replace(/\s+/g, " ")
                    .trim();


                if (!name) {
                    return;
                }


                /* Create product entry */

                products.push({

                    name: name,

                    url: new URL(
                        page +
                        "#product=" +
                        encodeURIComponent(name),
                        siteRoot
                    ).href

                });

            });

        } catch (error) {

            console.error(
                "Product search error:",
                page,
                error
            );

        }

    }


    /* Save index */

    productSearchIndex = products;


    console.log(
        "SMT Product Search Index:",
        productSearchIndex
    );

}


/* ============================================================
   INITIALIZE PRODUCT INDEX
   ============================================================ */

buildProductSearchIndex();


/* ============================================================
   NAVBAR PRODUCT SEARCH
   ============================================================ */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const searchInput =
            document.getElementById("productSearch");

        const searchResults =
            document.getElementById("searchResults");


        /* Stop if search elements don't exist */

        if (
            !searchInput ||
            !searchResults
        ) {

            return;
        }


        /* ====================================================
           SEARCH INPUT
           ==================================================== */

        searchInput.addEventListener(
            "input",
            function () {

                const query = searchInput.value
                    .toLowerCase()
                    .trim();


                /* Clear old results */

                searchResults.innerHTML = "";


                /* Hide dropdown if empty */

                if (!query) {

                    searchResults.classList.remove(
                        "active"
                    );

                    return;
                }


                /* =================================================
                   FIND MATCHING PRODUCTS
                   ================================================= */

                const matches =
                    productSearchIndex.filter(
                        function (product) {

                            return product.name
                                .toLowerCase()
                                .includes(query);

                        }
                    );


                /* =================================================
                   NO RESULTS
                   ================================================= */

                if (matches.length === 0) {

                    searchResults.innerHTML = `
                        <div class="search-result-item">
                            <span class="search-result-name">
                                No products found
                            </span>
                        </div>
                    `;

                    searchResults.classList.add(
                        "active"
                    );

                    return;
                }


                /* =================================================
                   SHOW MATCHING PRODUCTS
                   ================================================= */

                matches
                    .slice(0, 10)
                    .forEach(
                        function (product) {

                            const result =
                                document.createElement("a");


                            /* Result styling class */

                            result.className =
                                "search-result-item";


                            /* Product URL */

                            result.href =
                                product.url;


                            /* Product name */

                            result.innerHTML = `
                                <span class="search-result-name">
                                    ${product.name}
                                </span>

                                <span class="search-result-category">
                                    Product
                                </span>
                            `;


                            /* Add result */

                            searchResults.appendChild(
                                result
                            );

                        }
                    );


                /* Show dropdown */

                searchResults.classList.add(
                    "active"
                );

            }
        );


        /* ====================================================
           CLOSE SEARCH WHEN CLICKING OUTSIDE
           ==================================================== */

        document.addEventListener(
            "click",
            function (event) {

                if (
                    !event.target.closest(
                        ".search-wrapper"
                    )
                ) {

                    searchResults.classList.remove(
                        "active"
                    );

                }

            }
        );

    }
);


/* ============================================================
   SCROLL TO SEARCHED PRODUCT
   ============================================================ */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const hash =
            window.location.hash;


        /* ====================================================
           Check for product hash
           ==================================================== */

        if (
            !hash.startsWith(
                "#product="
            )
        ) {

            return;
        }


        /* ====================================================
           Get product name from URL
           ==================================================== */

        const productName =
            decodeURIComponent(
                hash.replace(
                    "#product=",
                    ""
                )
            )
            .trim();


        if (!productName) {
            return;
        }


        /* ====================================================
           Wait for page content
           ==================================================== */

        setTimeout(
            function () {


                /* Find ALL searchable products */

                const cards =
                    document.querySelectorAll(
                        ".searchable-product"
                    );


                let targetCard = null;


                /* =================================================
                   Find exact product
                   ================================================= */

                cards.forEach(
                    function (card) {

                        const title =
                            card.querySelector("h3");


                        if (!title) {
                            return;
                        }


                        const titleText =
                            title.textContent
                                .replace(/\s+/g, " ")
                                .trim();


                        /* Exact case-insensitive match */

                        if (
                            titleText.toLowerCase() ===
                            productName.toLowerCase()
                        ) {

                            targetCard = card;

                        }

                    }
                );


                /* =================================================
                   Product not found
                   ================================================= */

                if (!targetCard) {

                    console.warn(
                        "Search target product not found:",
                        productName
                    );

                    return;
                }


                /* =================================================
                   Scroll to exact product
                   ================================================= */

                targetCard.scrollIntoView({

                    behavior: "smooth",

                    block: "center"

                });


                /* =================================================
                   Optional visual highlight
                   ================================================= */

                targetCard.classList.add(
                    "search-target-highlight"
                );


                /* Remove highlight after a few seconds */

                setTimeout(
                    function () {

                        targetCard.classList.remove(
                            "search-target-highlight"
                        );

                    },
                    2500
                );


            },
            500
        );

    }
);