(function ($) {

    $.fn.simpleJson = function (options) {

        var settings = $.extend({
            collapsedText: "..."
        }, options);

        if (settings.jsonObject === undefined)
            throw "'jsonObject' must be supplied";

        return this.each(function () {
            var displayObject = settings.jsonObject;

            var rootNode = document.createElement("div");
            $(rootNode).addClass("simpleJson");
            showObject(rootNode, settings, displayObject);

            $(rootNode).find("span.simpleJson-collapsibleMarker").click(function (e) {
                var parentNode = $(e.currentTarget.parentNode);
                parentNode.toggleClass("simpleJson-collapsed");
            });
            this.appendChild(rootNode);
        });
    };

    function showObject(parentNode, settings, displayObject) {
        if (displayObject === undefined) {
            var span = document.createElement("span");
            span.innerText = "undefined";
            parentNode.appendChild(span);
            return;
        }

        if (Array.isArray(displayObject)) {
            var arrayStartSpan = document.createElement("span");
            arrayStartSpan.innerText = "[";
            var arrayEndSpan = document.createElement("span");
            arrayEndSpan.innerText = "]";


            var isCollapsible = isCollapsibleObject(displayObject);
            if (isCollapsible) {
                var collapsibleMarker = makeCollapsibleMarker(parentNode);
                parentNode.appendChild(collapsibleMarker);
            }
            parentNode.appendChild(arrayStartSpan);

            if (isCollapsible) {
                var collapsedText = makeCollapsedText(settings);
                parentNode.appendChild(collapsedText);
            }

            var ulElement = document.createElement("ul");
            $(ulElement).addClass("simpleJson-collapsibleContent");

            var arrayLength = displayObject.length;
            for (var idx = 0; idx < arrayLength; ++idx) {
                var liElement = document.createElement("li");
                ulElement.appendChild(liElement);

                showObject(liElement, settings, displayObject[idx]);

                if (idx !== arrayLength - 1) {
                    var commaSpan = document.createElement("span");
                    commaSpan.innerText = ",";
                    liElement.appendChild(commaSpan);
                }
            }

            parentNode.appendChild(ulElement);

            parentNode.appendChild(arrayEndSpan);
            return;
        }

        var type = typeof displayObject;
        if (type === "object") {

            var objectStartSpan = document.createElement("span");
            objectStartSpan.innerText = "{";
            var objectEndSpan = document.createElement("span");
            objectEndSpan.innerText = "}";

            var isCollapsible = isCollapsibleObject(displayObject);
            if (isCollapsible) {
                var collapsibleMarker = makeCollapsibleMarker();
                parentNode.appendChild(collapsibleMarker);
            }

            parentNode.appendChild(objectStartSpan);

            if (isCollapsible) {
                var collapsedText = makeCollapsedText(settings);
                parentNode.appendChild(collapsedText);

                var ulElement = document.createElement("ul");

                var keys = Object.keys(displayObject);
                var keysLength = keys.length;
                for (var idx = 0; idx < keysLength; ++idx) {
                    var liElement = document.createElement("li");
                    ulElement.appendChild(liElement);

                    var labelSpan = document.createElement("span");
                    labelSpan.innerText = "\"" + keys[idx] + "\": ";
                    $(labelSpan).addClass("simpleJson-key");

                    liElement.appendChild(labelSpan);
                    showObject(liElement, settings, displayObject[keys[idx]]);
                    if (idx !== keysLength - 1) {
                        var commaSpan = document.createElement("span");
                        commaSpan.innerText = ",";
                        liElement.appendChild(commaSpan);
                    }
                }

                parentNode.appendChild(ulElement);
            }

            parentNode.appendChild(objectEndSpan);
            return;
        } else {
            var span = document.createElement("span");

            switch (type) {
                case "string": {
                    span.innerText = "\"" + displayObject + "\"";
                    $(span).addClass("simpleJson-string");
                    break;
                }

                case "boolean": {
                    span.innerText = displayObject;
                    $(span).addClass("simpleJson-bool");
                    break;
                }

                default: {
                    span.innerText = displayObject;
                }
            }

            parentNode.appendChild(span);
            return;
        }
    }

    function makeCollapsibleMarker(parentNode) {
        var spanElement = document.createElement("span");
        spanElement.innerText = " ";
        $(spanElement).addClass("simpleJson-collapsibleMarker");
				$(parentNode).toggleClass("simpleJson-collapsed");
        return spanElement;
    }

    function makeCollapsedText(settings) {
        var collapsedText = document.createElement("span");
        collapsedText.innerText = settings.collapsedText;
        $(collapsedText).addClass("simpleJson-collapsedText");
        return collapsedText;
    }

    function isCollapsibleObject(displayObject) {

        if (displayObject === undefined || displayObject === null)
            return false;

        if (Array.isArray(displayObject) )
            return displayObject.length > 0;

        if ((typeof displayObject) === "object") {
            return Object.keys(displayObject).length > 0;
        }

        return false;
    }

}(jQuery));