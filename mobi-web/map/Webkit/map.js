// Set initial values for drawing the map image
var mapW, mapH;	// integers: width and height of map image
var zoom = 0; // integer: zoom level -- should always default to 0
var mapBoxW = initMapBoxW;	// integer: western bound of map image (per IMS map API) 
var mapBoxN = initMapBoxN;	// integer: northern bound of map image (per IMS map API)
var mapBoxS = initMapBoxS;	// integer: southern bound of map image (per IMS map API)
var mapBoxE = initMapBoxE;	// integer: eastern bound of map image (per IMS map API)
var hasMoved = false;	// boolean: has the map been scrolled or zoomed?
var maxZoom = 2;	// integer: max zoom-in level
var minZoom = -8;	// integer: max zoom-out level

// label (*-iden-*) layers.  should stop hard coding
var hiddenLabels = "";
var labels14 = "15,19,23,27,31";
var labels12 = "14,18,22,26,30";
var labels10 = "13,17,21,25,29";
var labels8 = "12,16,20,24,28";

function jumpbrowse(objSelect) {
// Use the value of the 'browse by' select control to jump to a different browse page
	if(objSelect) {
		switch(objSelect.value) {
			case "number":
				document.location.href="building-number.html";
				break;
			case "name":
				document.location.href="building-name.html";
				break;
			case "residences":
				document.location.href="residences.html";
				break;
			case "rooms":
				document.location.href="rooms.html";
				break;
			case "streets":
				document.location.href="streets.html";
				break;
			case "courts":
				document.location.href="courts.html";
				break;
			case "food":
				document.location.href="food.html";
				break;
			case "parking":
				document.location.href="parking.html";
				break;
		}
	}
}


function loadImage(imageURL,imageID) {
// Loads an image from the given URL into the image with the specified ID
	var objMap = document.getElementById(imageID);
	show("loadingimage");
	if(objMap) {
		if(imageURL!="") {
			objMap.src = imageURL;
		} else {
			objMap.src = "../Webkit/images/blank.png";
		}
	}
	// Since we're loading a new map image, update the link(s) to switch between fullscreen and smallscreen modes
	var objFullscreen = document.getElementById("fullscreen");
	if(objFullscreen) {
		objFullscreen.href = getMapURL(fullscreenBaseURL, true);
		hiddenLabels = "";
	}
	var objSmallscreen = document.getElementById("smallscreen");
	if(objSmallscreen) {
		objSmallscreen.href = getMapURL(detailBaseURL, true);
	}
}


function getMapURL(strBaseURL, includeSelect) {
	var labelLayer;
	var xRange = mapBoxE - mapBoxW;

	if (xRange < 600)     labelLayer = labels14;
        else if (xRange < 900) labelLayer = labels12;
	else if (xRange < 1200) labelLayer = labels10;
	else                   labelLayer = labels8;

	var labels = labelLayer.split(",");
	var hiddenLabelArr = hiddenLabels.split(",");
	if (hiddenLabels.length > 0) {
		var newLabels = new Array();
		for (var labelIndex in labels) {
			var keepLabel = 1;
			for (var hiddenIndex in hiddenLabelArr) {
				if (labels[labelIndex] == hiddenLabelArr[hiddenIndex]) {
					keepLabel = 0;
				}
			}
			if (keepLabel) {
				newLabels.push(labels[labelIndex]);
			}
		}
		labelLayer = newLabels.join(",");
		labels = newLabels;
	}

	// Returns a full URL for a map page or map image, using strBaseURL as the base
	var layerCount = mapLayers.split(",").length + labels.length;
	if (labelLayer.length > 0) { labelLayer = "," + labelLayer; }
	var mapStyles = "";
	if (layerCount > 0) {
	        mapStyles = "default";
		for (i = 1; i < layerCount; i++) {
			mapStyles = mapStyles + ",default";
		}
	}

	var newURL = strBaseURL + "&width=" + mapW + "&height=" + mapH + "&selectvalues=" + mapSelect + "&bbox=" + mapBoxW + "," + mapBoxS + "," + mapBoxE + "," + mapBoxN + "&layers=" + mapLayers + labelLayer + "&styles=" + mapStyles + mapOptions;

        // Add parameters for the original bounding box, so Image can be recentered
        if(includeSelect) {
        	newURL += "&bboxSelect=" + selectMapBoxW + "," + selectMapBoxS + "," + selectMapBoxE + "," + selectMapBoxN;
        }
	return(newURL);
}


function scroll(dir) {
// Scrolls the map image in the cardinal direction given by dir;
// amount of scrolling is scaled to projected map image dimensions
	var objMap = document.getElementById("mapimage");
	if(objMap) {
		var mapDX, mapDY;
		//if(zoom<maxZoom) {
		//	mapDX = mapW*(1-(zoom/2));
		//	mapDY = mapH*(1-(zoom/2));
		//} else {
		//	mapDX = mapW/2.3;
		//	mapDY = mapH/2.3;
		//}
		mapDX = (mapBoxN - mapBoxS) / 2;
		mapDY = (mapBoxE - mapBoxW) / 2;
		switch(dir) {
			case "n":
				mapBoxN = mapBoxN + mapDY;
				mapBoxS = mapBoxS + mapDY;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
			case "s":
				mapBoxN = mapBoxN - mapDY;
				mapBoxS = mapBoxS - mapDY;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
			case "e":
				mapBoxE = mapBoxE + mapDX;
				mapBoxW = mapBoxW + mapDX;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
			case "w":
				mapBoxE = mapBoxE - mapDX;
				mapBoxW = mapBoxW - mapDX;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
			case "ne":
				mapBoxN = mapBoxN + mapDY;
				mapBoxS = mapBoxS + mapDY;
				mapBoxE = mapBoxE + mapDX;
				mapBoxW = mapBoxW + mapDX;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
			case "nw":
				mapBoxN = mapBoxN + mapDY;
				mapBoxS = mapBoxS + mapDY;
				mapBoxE = mapBoxE - mapDX;
				mapBoxW = mapBoxW - mapDX;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
			case "se":
				mapBoxN = mapBoxN - mapDY;
				mapBoxS = mapBoxS - mapDY;
				mapBoxE = mapBoxE + mapDX;
				mapBoxW = mapBoxW + mapDX;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
			case "sw":
				mapBoxN = mapBoxN - mapDY;
				mapBoxS = mapBoxS - mapDY;
				mapBoxE = mapBoxE - mapDX;
				mapBoxW = mapBoxW - mapDX;
				loadImage(getMapURL(mapBaseURL),'mapimage');
				break;
		}
		checkIfMoved();		
	}
}


function recenter() {
// Reset the map image to its initially selected coordinates -- only if it's not already there
	if(hasMoved) {
		hasMoved = false;
		mapBoxW = selectMapBoxW;
		mapBoxN = selectMapBoxN;
		mapBoxS = selectMapBoxS;
		mapBoxE = selectMapBoxE;
		zoom = 0;	// reset zoom level
		loadImage(getMapURL(mapBaseURL),'mapimage');
		enable('zoomin');
		enable('zoomout');
		disable('recenter');
	} 
}


function zoomout() {
// Zoom the map out by an amount scaled to the pixel dimensions of the map image
	enable('zoomin');
	var mapBoxHeight = mapBoxN - mapBoxS;
	var mapBoxWidth = mapBoxE - mapBoxW;
	if(zoom > minZoom) {
		mapBoxN = mapBoxN + (mapBoxHeight/2);
		mapBoxS = mapBoxS - (mapBoxHeight/2);
		mapBoxE = mapBoxE + (mapBoxWidth/2);
		mapBoxW = mapBoxW - (mapBoxWidth/2);
		loadImage(getMapURL(mapBaseURL),'mapimage');
		zoom--;
	}
	if(zoom <= minZoom) {	// If we've reached the min zoom level
		disable('zoomout');
	}
	checkIfMoved();		
}


function zoomin() {
// Zoom the map in by an amount scaled to the pixel dimensions of the map image
	enable('zoomout');
	var mapBoxHeight = mapBoxN - mapBoxS;
	var mapBoxWidth = mapBoxE - mapBoxW;
	if(zoom < maxZoom) {
		mapBoxN = mapBoxN - (mapBoxHeight/4);
		mapBoxS = mapBoxS + (mapBoxHeight/4);
		mapBoxE = mapBoxE - (mapBoxWidth/4);
		mapBoxW = mapBoxW + (mapBoxWidth/4);
		loadImage(getMapURL(mapBaseURL),'mapimage');
		zoom++;
	}
	if(zoom >= maxZoom) {	// If we've reached the max zoom level
		disable('zoomin');
	}
	checkIfMoved();		
}

function rotateMap() {
// Load a rotated map image
	var objMap = document.getElementById("mapimage");
	var objContainer = document.getElementById("container");
	var objScrollers = document.getElementById("mapscrollers");
	if(objMap) {
		show("loadingimage");
		mapW = window.innerWidth;
		mapH = window.innerHeight;
		loadImage(getMapURL(mapBaseURL),'mapimage'); 
	}
	if(objContainer) {
		objContainer.style.width=mapW+"px";
		objContainer.style.height=mapH+"px";
		objMap.style.width=mapW+"px";
		objMap.style.height=mapH+"px";
	}
	if(objScrollers) {
		switch(window.orientation)
		{
			case 0:
			case 180:
				objScrollers.style.height=(mapH-42)+"px";
			break;
	
			case -90:
			case 90:
				objScrollers.style.height=mapH+"px";
			break;
	
		}
	}
}

function rotateMapAlternate() {
// Load a rotated map image - needs work to get innerWidth and innerHeight working correctly -- will be required once firmware 2.0 is released enabling full-screen chromeless browsing
	var objMap = document.getElementById("mapimage");
	if(objMap) {
		show("loadingimage");
		mapW = window.innerWidth;
		mapH = window.innerHeight;
		loadImage(getMapURL(mapBaseURL),'mapimage'); 
		alert(mapW + " x " + mapH);
	}
}



function checkIfMoved() {
// Check to see if the map has been moved (zoomed or scrolled) away from its initial position, and disable/enable the 'recenter' button accordingly
	hasMoved = !((mapBoxW == selectMapBoxW) && (mapBoxN == selectMapBoxN) && (mapBoxS == selectMapBoxS) && (mapBoxE == selectMapBoxE));
	if(hasMoved) {
		enable('recenter');
	} else {
		disable('recenter');
	}

}


function disable(strID) {
// Visually dims and disables the anchor whose id is strID
	var objA = document.getElementById(strID);
	if(objA) {
		if(objA.className.indexOf("disabled") == -1) { // only disable if it's not already disabled!
			objA.className = objA.className + " disabled";
		}
	}
}


function enable(strID) {
// Visually undims and re-enables the anchor whose id is strID
	var objA = document.getElementById(strID);
	if(objA) {
		objA.className = objA.className.replace("disabled","");
	}
}


function saveOptions(strFormID) {
// Applies full-screen map-option changes and hides the form
	// Code to manipulate the string newLayers should go here, based on what the user toggled in the form
	var newHiddenArray = new Array();
	if(!document.mapform.chkLabelBuildings.checked) {
		newHiddenArray.push("12,13,14,15"); // all bldg-iden layers
	}
	if(!document.mapform.chkLabelRoads.checked) {
		newHiddenArray.push("16,17,18,19"); // all road-iden layers
	}
	if(!document.mapform.chkLabelCourts.checked) {
		newHiddenArray.push("20,21,22,23"); // all greens-iden layers
	}
	if(!document.mapform.chkLabelLandmarks.checked) {
		newHiddenArray.push("24,25,26,27"); // all landmarks-iden layers
	}
	if(!document.mapform.chkLabelParking.checked) {
		newHiddenArray.push("28,29,30,31"); // all parking-iden layers
	}
	
	var newHiddenLabels = newHiddenArray.join(",");
	if (newHiddenLabels != hiddenLabels) {
		hiddenLabels = newHiddenLabels;
		loadImage(getMapURL(mapBaseURL),'mapimage'); 
	}

	hide("options");
}


function cancelOptions(strFormID) {
// Should cancel map-option changes and hide the form; this is just a stub for future real function
	var objForm = document.getElementById(strFormID);
	if(objForm) { objForm.reset() }
	hide("options"); 
}


