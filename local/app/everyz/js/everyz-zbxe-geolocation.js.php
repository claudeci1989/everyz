<script type="text/javascript">

    /* *************************************************************************
     * 
     * Dynamic Data 
     * 
     * ********************************************************************** */
    //Parametrizar
    var setViewLat = <?php echo $filter['centerLat'] ?>;
    var setViewLong = <?php echo $filter['centerLong'] ?>;
    var setViewZoom = <?php echo getRequest2("zoomLevel", 19)?>;
    var showCircles = <?php echo ( in_array($filter["layers"], [3, 99]) ? 1 : 0); ?>; //(0=disable/1=enable)
    var showLines = <?php echo ( in_array($filter["layers"], [2, 99]) ? 1 : 0); ?>; //(0=disable/1=enable)

    vDiv = document.getElementById("mapid");

    vDiv.style.width = screen.availWidth;
    vDiv.style.height = screen.availHeight;
    //Define area for Map (setup this data in database ZabbixExtras)
    var ZabGeomap = L.map('mapid').setView([setViewLat, setViewLong], setViewZoom);

    //Create layerGroup Circle
    var ZabGeocircle = new L.LayerGroup();

    //Create layerGroup Circle
    var ZabGeolines = new L.LayerGroup();


    //User will need change this token for the theirs token, acquire in https://www.mapbox.com/studio/account/
    //(setup this data in database ZabbixExtras)
    //pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpandmbXliNDBjZWd2M2x6bDk3c2ZtOTkifQ._QA7i5Mpkd_m30IGElHziw
    var mbToken = '<?php echo zbxeConfigValue('geo_token') ?>';

    var mbAttr = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a> contributors, ' +
            '<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery &copy <a href="http://mapbox.com">Mapbox</a>',
            mbUrl = 'https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=' + mbToken;


    //Display Copyright 
    L.tileLayer(mbUrl, {
        maxZoom: 18,
        attribution: mbAttr,
        id: 'mapbox.streets'
    }).addTo(ZabGeomap);
    // Cria dinamicamente a referencia para o icone do host
    function zbxImage(p_iconid) {
        return L.icon({
            iconUrl: '/imgstore.php?iconid=' + p_iconid,
            iconSize: [28, 34],
//            iconSize: [32, 44],
            iconAnchor: [14, 34],
            popupAnchor: [2, -38],
        });
    }
    //
    //Change for repeat to read JSON and add Markers if lat and long exist
    //Put marker in Map
<?php
// Cria os hosts no mapa
$linesPackage = "";
foreach ($hostData as $host) {
    if (array_key_exists("location_lat", $host)) {
        // Add host
        echo "L.marker([" . $host["location_lat"] . ", "
        . $host["location_lon"] . "], {icon: zbxImage(" . $host["iconid"] . ")}).addTo(ZabGeomap).bindPopup('" . $host["name"]
//        . $host["location_lon"] . "], {icon: zbxIconOk}).addTo(ZabGeomap).bindPopup('" . $host["name"]
        . "<br>IP: 192.168.1.100');\n";
        // Add circles
        if (isset($host["circle"])) {
            foreach ($host["circle"] as $circles) {
                echo "L.circle([" . $host["location_lat"] . ", "
                . $host["location_lon"] . "], {color: '" . $circles[2]
                . "', fillColor: '#303', fillOpacity: 0.2, radius: " . $circles[1] . "}).addTo(ZabGeocircle);\n";
            }
        }
        // Add lines
        if (isset($host["line"])) {
            $lineCount = 1;
            foreach ($host["line"] as $lines) {
                $linesPackage .= ($linesPackage == "" ? "" : ", ")
                        . "\n" . '{"type": "Feature", "geometry": { "type": "LineString", "coordinates": [['
                        . $host["location_lon"] . ", " . $host["location_lat"]
                        . '],[' . $lines[2] . ', ' . $lines[1] . ']]}, "properties": { "popupContent": "' . $lines[5] . '"},"id": '
                        . $lineCount . '}';
                $lineCount++;
            }
        }
    }
}
?>

    //Change for repeat to read JSON and add Circle inf note exist
    //If radius exist add Circle [use lat and long more radius]
    //Capture point in map using double click 
    var popup = L.popup();
    function onMapClick(e) {
        popup
                .setLatLng(e.latlng)
                .setContent("You selected here: " + e.latlng.toString())
                .openOn(ZabGeomap);
    }

    ZabGeomap.on('contextmenu', onMapClick);

    //Add Scale in maps
    L.control.scale().addTo(ZabGeomap);

    // Mapas disponíveis =======================================================
    var grayscale = L.tileLayer(mbUrl, {id: 'mapbox.light', attribution: mbAttr})
            , streets = L.tileLayer(mbUrl, {id: 'mapbox.streets', attribution: mbAttr})
            , dark = L.tileLayer(mbUrl, {id: 'mapbox.dark', attribution: mbAttr})
            , outdoors = L.tileLayer(mbUrl, {id: 'mapbox.outdoors', attribution: mbAttr})
            , satellite = L.tileLayer(mbUrl, {id: 'mapbox.satellite', attribution: mbAttr})
            , emerald = L.tileLayer(mbUrl, {id: 'mapbox.emerald', attribution: mbAttr})
            ;

    var baseMaps = {
        "Grayscale": grayscale
        , "Streets": streets
        , "Dark": dark
        , "Outdoors": outdoors
        , "Satellite": satellite
        , "Emerald": emerald
    };



    var overlayMaps = {
        "Circles": ZabGeocircle,
        "Lines": ZabGeolines,
    };

    layerControl = L.control.layers(baseMaps).addTo(ZabGeomap);

    if (showCircles == 1) {
        ZabGeomap.addLayer(ZabGeocircle);
    }

    if (showLines == 1) {
        ZabGeomap.addLayer(ZabGeolines);
    }

    layerControl.addOverlay(ZabGeocircle, "Circle");
    layerControl.addOverlay(ZabGeolines, "Lines");
    //Add lines between hosts
    var lineHosts = {
        "type": "FeatureCollection",
        "features": [
<?php echo $linesPackage; ?>

        ]
    };

    function onEachFeature(feature, layer) {
        var popupContent = "";

        if (feature.properties && feature.properties.popupContent) {
            popupContent += feature.properties.popupContent;
        }

        layer.bindPopup(popupContent);
    }


    L.geoJSON(lineHosts, {
        onEachFeature: onEachFeature
    }).addTo(ZabGeolines);


</script>