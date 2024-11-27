var $addonsContainer = document.getElementById("mosh-products-container");
if ($addonsContainer) {
    // On keyup of mosh-products-search input, search if any matches that of $addonsContainer and hide those that are not a match.

    var searchInput = document.getElementById("mosh-products-search");
    var products = document.querySelectorAll(".mosh-product");

    document
        .getElementById("mosh-products-search")
        .addEventListener("keyup", function () {
            var searchValue = this.value.toLowerCase();
            products.forEach(function (product) {
                var productName = product
                    .querySelector(".mosh-product-name")
                    .textContent.toLowerCase();
                if (productName.includes(searchValue)) {
                    product.style.display = "block";
                } else {
                    product.style.display = "none";
                }
            });
        });

    document
        .getElementById("mosh-products-search")
        .addEventListener("input", function () {
            if (this.value === "") {
                products.forEach(function (product) {
                    product.style.display = "block";
                });
            }
        });

    if (typeof matchHeight === "function") {
        var productDetails = document.querySelectorAll(
            ".mosh-product .mosh-product-details"
        );
        productDetails.forEach(function (detail) {
            detail.style.height = "auto";
        });
    }

    var icons = {
        activate:
            '<i class="dashicons dashicons-yes-alt" aria-hidden="true"></i>',
        deactivate:
            '<i class="dashicons dashicons-no-alt" aria-hidden="true"></i>',
        install:
            '<i class="dashicons dashicons-download" aria-hidden="true"></i>',
        spinner: '<i aria-hidden="true">Processing..</i>',
    };

    document.addEventListener("click", function (event) {
        if (event.target.matches(".mosh-product-action button")) {
            var $button = event.target,
                $addon = $button.closest(".mosh-product"),
                originalButtonHtml = $button.innerHTML,
                originalButtonWidth = $button.offsetWidth,
                type = $button.dataset.type,
                action,
                statusClass,
                statusText,
                buttonHtml,
                successText;

            if ($addon.classList.contains("mosh-product-status-active")) {
                action = "mosh_addon_deactivate";
                statusClass = "mosh-product-status-inactive";
                statusText = MoshAddons.inactive;
                buttonHtml = icons.activate + MoshAddons.activate;
            } else if (
                $addon.classList.contains("mosh-product-status-inactive")
            ) {
                action = "mosh_addon_activate";
                statusClass = "mosh-product-status-active";
                statusText = MoshAddons.active;
                buttonHtml = icons.deactivate + MoshAddons.deactivate;
            } else if (
                $addon.classList.contains("mosh-product-status-download")
            ) {
                action = "mosh_addon_install";
                statusClass = "mosh-product-status-active";
                statusText = MoshAddons.active;
                buttonHtml = icons.deactivate + MoshAddons.deactivate;
            } else {
                return;
            }

            $button.disabled = true;
            $button.innerHTML = icons.spinner;
            $button.classList.add("mosh-loading");
            $button.style.width = originalButtonWidth + "px";

            var data = {
                action: action,
                _ajax_nonce: MoshAddons.nonce,
                plugin: $button.dataset.plugin,
                type: type,
            };

            var handleError = function (message) {
                var messageDiv = document.createElement("div");
                messageDiv.className =
                    "mosh-product-message mosh-product-message-error";
                messageDiv.textContent = message;
                $addon
                    .querySelector(".mosh-product-actions")
                    .appendChild(messageDiv);
                $button.innerHTML = originalButtonHtml;
            };

            var formData = new FormData();
            formData.append("action", data.action);
            formData.append("_ajax_nonce", data._ajax_nonce);
            formData.append("plugin", data.plugin);
            formData.append("type", data.type);
            fetch(MoshAddons.ajax_url, {
                method: "POST",
                body: formData,
            })
                .then((response) => response.json())
                .then((response) => {
                    if (
                        !response ||
                        typeof response !== "object" ||
                        typeof response.success !== "boolean"
                    ) {
                        handleError(
                            type === "plugin"
                                ? MoshAddons.plugin_install_failed
                                : MoshAddons.install_failed
                        );
                    } else if (!response.success) {
                        if (
                            typeof response.data === "object" &&
                            response.data[0] &&
                            response.data[0].code
                        ) {
                            handleError(
                                type === "plugin"
                                    ? MoshAddons.plugin_install_failed
                                    : MoshAddons.install_failed
                            );
                        } else {
                            handleError(response.data);
                        }
                    } else {
                        if (action === "mosh_addon_install") {
                            $button.dataset.plugin = response.data.basename;
                            successText = response.data.message;

                            if (!response.data.activated) {
                                statusClass = "mosh-product-status-inactive";
                                statusText = MoshAddons.inactive;
                                buttonHtml =
                                    icons.activate + MoshAddons.activate;
                            }
                        } else {
                            successText = response.data;
                        }

                        var successDiv = document.createElement("div");
                        successDiv.className =
                            "mosh-product-message mosh-product-message-success";
                        successDiv.textContent = successText;
                        $addon
                            .querySelector(".mosh-product-actions")
                            .appendChild(successDiv);

                        $addon.classList.remove(
                            "mosh-product-status-active",
                            "mosh-product-status-inactive",
                            "mosh-product-status-download"
                        );
                        $addon.classList.add(statusClass);
                        $addon.querySelector(
                            ".mosh-product-status-label"
                        ).textContent = statusText;
                        $button.innerHTML = buttonHtml;
                    }
                })
                .catch(() => {
                    handleError(
                        type === "plugin"
                            ? MoshAddons.plugin_install_failed
                            : MoshAddons.install_failed
                    );
                })
                .finally(() => {
                    $button.disabled = false;
                    $button.classList.remove("mosh-loading");
                    $button.style.width = "auto";

                    // Automatically clear add-on messages after 3 seconds
                    setTimeout(function () {
                        $addon
                            .querySelectorAll(".mosh-product-message")
                            .forEach(function (msg) {
                                msg.remove();
                            });
                    }, 3000);
                });
        }
    });
}
