{literal}
var viewer = Mirador.viewer({
    "id": "mirador-viewer",
    "windows": [{
        "manifestId": "/Archive/AJAX?method=fetchManifest&nid={/literal}{$nid}{literal}",
        "view": 'single',
    }],
    "window": {
        "allowClose": false, // Prevent the user from closing the view
        "allowMaximize": false,
        "allowFullscreen": true,
        "defaultSideBarPanel": 'info',
        "sideBarOpenByDefault": false,
        "hideWindowTitle": true,
        "views": [
            { "key": 'single' },
            { "key": 'gallery' },
            { "key": 'book' },
        ]
    },
    "workspace": {
        "type": 'mosaic',
    },
    "workspaceControlPanel": {
        "enabled": false, // Remove extra workspace settings
    },
    thumbnailNavigation: {
        defaultPosition: 'far-bottom', // Which position for the thumbnail navigation to be be displayed. Other possible values are "far-bottom" or "far-right"
        displaySettings: false, // Display the settings for this in WindowTopMenu
        height: 80, // height of entire ThumbnailNavigation area when position is "far-bottom"
        showThumbnailLabels: true, // Configure if thumbnail labels should be displayed
        width: 60, // width of one canvas (doubled for book view) in ThumbnailNavigation area when position is "far-right"
    },
    "theme": {
        "typography": {
            body1: {
                fontSize: "1.215rem",
                letterSpacing: "0em",
                lineHeight: "1.4em",
            },
            body2: {
                fontSize: "1rem",
                letterSpacing: "0.015em",
                lineHeight: "1.6em",
            },
            button: {
                fontSize: "0.878rem",
                letterSpacing: "0.09em",
                lineHeight: "2.25rem",
                textTransform: "uppercase",
            },
            caption: {
                fontSize: "0.772rem",
                letterSpacing: "0.033em",
                lineHeight: "1.6rem",
            },
            body1Next: {
                fontSize: "1rem",
                letterSpacing: "0em",
                lineHeight: "1.6em",
            },
            body2Next: {
                fontSize: "0.878rem",
                letterSpacing: "0.015em",
                lineHeight: "1.6em",
            },
            buttonNext: {
                fontSize: "0.878rem",
                letterSpacing: "0.09em",
                lineHeight: "2.25rem",
            },
            captionNext: {
                fontSize: "0.772rem",
                letterSpacing: "0.33em",
                lineHeight: "1.6rem",
            },
            overline: {
                fontSize: "0.9rem",
                fontWeight: 800,
                letterSpacing: "0.166em",
                lineHeight: "2em",
                textTransform: "uppercase",
            },
            h1: {
                fontSize: "2.822rem",
                letterSpacing: "-0.015em",
                lineHeight: "1.2em",
            },
            h2: {
                fontSize: "1.575rem",
                letterSpacing: "0em",
                lineHeight: "1.33em",
            },
            h3: {
                fontSize: "1.383rem",
                fontWeight: 300,
                letterSpacing: "0em",
                lineHeight: "1.33em",
            },
            h4: {
                fontSize: "1.215rem",
                letterSpacing: "0.007em",
                lineHeight: "1.45em",
            },
            h5: {
                fontSize: "1.138rem",
                letterSpacing: "0.005em",
                lineHeight: "1.55em",
            },
            h6: {
                fontSize: "1.067rem",
                fontWeight: 400,
                letterSpacing: "0.01em",
                lineHeight: "1.6em",
            },
            subtitle1: {
                fontSize: "0.937rem",
                letterSpacing: "0.015em",
                lineHeight: "1.6em",
                fontWeight: 300,
            },
            subtitle2: {
                fontSize: "0.878rem",
                fontWeight: 500,
                letterSpacing: "0.02em",
                lineHeight: "1.75em",
            },
        },
    },
});
{/literal }