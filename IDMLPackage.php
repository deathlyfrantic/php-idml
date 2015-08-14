<?php
namespace IDMLPackage; 
/**
 * An object that handles all of the individual files in an IDML package.
 * This is a very simple class that was developed as part of a much larger
 * project. It basically only serves as a file manager/server for the component
 * XML files of an IDML package.
 *
 * As far as I am aware the only dependencies are PHP's XML module and
 * the zip/unzip utilities. (Generally just install zip with your package manager.)
 *
 * Any methods that aren't specifically getters should return $this to facilitate
 * method chaining.
 */
class IDMLPackage
{
    protected $designMap;
    protected $masterSpreads = [];
    protected $graphic;
    protected $fonts;
    protected $styles;
    protected $preferences;
    protected $spreads = [];
    protected $stories = [];
    protected $backingStory;
    protected $tags;
    protected $mapping;
    protected $directory;
    protected $zip;

    /**
     * This is an array used to map package elements to their respective load methods. 
     * See $this->load(). I am lazy and hate typing.
     */
    protected $packageElements = [
        "Graphic" => "set",
        "Fonts" => "set",
        "Styles" => "set",
        "Preferences" => "set",
        "Tags" => "set",
        "MasterSpread" => "add",
        "Spread" => "add",
        "BackingStory" => "set",
        "Story" => "add",
        "Mapping" => "set"
    ];

    /**
     * If the parameter is an IDML file - and there is not a ton of checking that goes into that -
     * pass it to setZip() which unzips it and loads up stuff. Otherwise, if it's a directory,
     * load it from that.
     * The project I developed this for uses IDML packages that live in an unzipped form
     * so they can be easily-modified before zipping up for processing.
     */
    public function __construct($val = "")
    {
        if(mb_strpos($val, ".idml") !== false && file_exists($val)) {
            $this->setZip($val)->load();
        } elseif(is_dir($val)) {
            $this->setDirectory($val)->load();
        }
    }

    /**
     * Basically if this package was loaded from an IDML file, we're assuming we unzipped it,
     * so delete all the temp directory and files we created by unzipping it.
     */
    public function __destruct()
    {
        if($this->isZip() && is_dir($this->getDirectory())) {
            exec("rm -rf " . $this->getDirectory());
        }
    }

    /**
     * Get the design map of the IDML.
     * @return DOMDocument The designmap.xml of the IDML package.
     */
    public function getDesignMap()
    {
        return $this->designMap;
    }

    /**
     * Set the design map of the IDML.
     * @param DOMDocument $val
     * The DOMDocument object loaded with the designmap.xml file.
     */
    public function setDesignMap(\DOMDocument $val)
    {
        $this->designMap = $val;
        return $this;
    }

    /**
     * Get either a single master spread or an array of master spreads.
     * @param string $which [optional]
     * The self id of the master spread you wish to return.
     * @return [mixed] Without $which parameter, returns an array of all master spreads.
     * With $which parameter, returns a single DOMDocument of the specified master spread.
     */
    public function getMasterSpreads($which = "")
    {
        if(array_key_exists($which, $this->masterSpreads)) {
            return $this->masterSpreads[$which];
        }
        return $this->masterSpreads;
    }

	/**
	 * Master spreads setter. If you only have one, wrap it in [] before passing.
	 * @param array $val The array of master spreads.
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setMasterSpreads(array $val)
    {
        $this->designMap = $val;
        return $this;
    }

	/**
	 * Returns the DOMDocument object of the Graphic.xml file.
	 */
    public function getGraphic()
    {
        return $this->graphic;
    }

	/**
	 * Graphic setter. 
	 * @param \DOMDocument $val The Graphic.xml file's DOMDocument. 
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setGraphic(\DOMDocument $val)
    {
        $this->graphic = $val;
        return $this;
    }

	/**
	 * Returns the DOMDocument object of the Fonts.xml file.
	 */
    public function getFonts()
    {
        return $this->fonts;
    }

	/**
	 * Fonts setter. 
	 * @param \DOMDocument $val The Fonts.xml file's DOMDocument. 
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setFonts(\DOMDocument $val)
    {
        $this->fonts = $val;
        return $this;
    }

	/**
	 * Returns the DOMDocument object of the Styles.xml file.
	 */
    public function getStyles()
    {
        return $this->styles;
    }

	/**
	 * Styles setter. 
	 * @param \DOMDocument $val The Styles.xml file's DOMDocument. 
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setStyles(\DOMDocument $val)
    {
        $this->styles = $val;
        return $this;
    }

	/**
	 * Returns the DOMDocument object of the Preferences.xml file.
	 */
    public function getPreferences()
    {
        return $this->preferences;
    }

	/**
	 * Preferences setter. 
	 * @param \DOMDocument $val The Preferences.xml file's DOMDocument. 
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setPreferences(\DOMDocument $val)
    {
        $this->preferences = $val;
        return $this;
    }

    /**
     * Get either a single spread or an array of spreads.
     * @param string $which [optional]
     * The self id of the spread you wish to return.
     * @return [mixed] Without $which parameter, returns an array of all spreads.
     * With $which parameter, returns a single DOMDocument of the specified spread.
     */
    public function getSpreads($which = "")
    {
        if(array_key_exists($which, $this->spreads)) {
            return $this->spreads[$which];
        }
        return $this->spreads;
    }

	/**
	 * Spreads setter. If you only have one, wrap it in [] before passing.
	 * @param array $val The array of spreads.
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setSpreads(array $val)
    {
        $this->spreads = $val;
        return $this;
    }

    public function getStories($which = "")
    {
        if(array_key_exists($which, $this->stories)) {
            return $this->stories[$which];
        }
        return $this->stories;
    }

	/**
	 * Stories setter. If you only have one, wrap it in [] before passing.
	 * @param array $val The array of stories.
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setStories(array $val)
    {
        $this->stories = $val;
        return $this;
    }

	/**
	 * Returns the DOMDocument object of the BackingStory.xml file.
	 */
    public function getBackingStory()
    {
        return $this->backingStory;
    }

	/**
	 * Backing story setter. 
	 * @param \DOMDocument $val The BackingStory.xml file's DOMDocument. 
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setBackingStory(\DOMDocument $val)
    {
        $this->backingStory = $val;
        return $this;
    }

	/**
	 * Returns the DOMDocument object of the Tags.xml file.
	 */
    public function getTags()
    {
        return $this->tags;
    }

	/**
	 * Tags setter. 
	 * @param \DOMDocument $val The Tags.xml file's DOMDocument. 
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setTags(\DOMDocument $val)
    {
        $this->tags = $val;
        return $this;
    }

	/**
	 * Returns the DOMDocument object of the Mapping.xml file.
	 */
    public function getMapping()
    {
        return $this->mapping;
    }

	/**
	 * Mapping setter. 
	 * @param \DOMDocument $val The Mapping.xml file's DOMDocument. 
	 * @return IDMLPackage\IDMLPackage This object for method chaining.
	 */
    public function setMapping(\DOMDocument $val)
    {
        $this->mapping = $val;
        return $this;
    }

	/**
	 * Returns a string containing the directory name of this IDML.
	 */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Set the directory for this IDML package. Basically if you have /directory/idmlfile.idml
     * and unzipped it, you'd setDirectory("/directory/").
     */
    public function setDirectory($val)
    {
        if(is_dir($val) || is_null($val)) {
            $this->directory = $val;
            if(is_null($val) === false && mb_substr($val, -1) !== "/") {
                $this->directory .= "/";
            }
        }
        return $this;
    }

	/**
	 * Returns a string containing the zip file name of this IDML.
	 */
    public function getZip()
    {
        return $this->zip;
    }

    /**
     * Set the zip file for this package. If you have /directory/idmlfile.idml, you'd
     * setZip("/directory/idmlfile.idml"). This would then unzip that file to /directory/.idmlfile.idml/
     * and load all the stuff.
     * The __destruct() method ensures this temporary directory will be deleted upon object destruction.
     */
    public function setZip($val)
    {
        $this->zip = realpath($val);
        if(empty($this->getDirectory()) && $this->getDirectory() !== 0) { 
            // gotta love empty() and PHP's automatic type-conversion :/
            $directoryName = dirname($val) . "/." . basename($val);
            mkdir($directoryName, 0775);
            $this->setDirectory($directoryName);
            exec("unzip -o -d $directoryName $val"); 
            // gotta have zip installed on your system, sorry
        }
        return $this;
    }

	/**
	 * Set all array properties to empty arrays.
     */
    public function unsetArrays()
    {
        $this->setSpreads([])
            ->setMasterSpreads([])
            ->setStories([]);
        return $this;
    }

	/**
	 * Special method to load the designmap.xml of this IDML which is required to get all of the other components.
	 */
    public function loadDesignMap()
    {
        $designmap = $this->createDom($this->getDirectory() . "designmap.xml");
        $this->setDesignMap($designmap);
        return $this;
    }

    /**
     * This is the master load method that will create populate the object with
     * DOMDocuments of the component XML files. You need to set the location of
     * the IDML before this method is called - either by passing it with the 
     * instantiation (new IDMLPackage\IDMLPackage("idmlfile.idml")) or by calling
     * the setZip() or setDirectory() methods.
     */
    public function load()
    {
        // since some files are appended to arrays, let's unset those arrays when we load just to be safe
        $this->unsetArrays();
        if($this->getDesignMap() instanceof \DOMDocument == false) {
            $this->loadDesignMap();
        }
        $xpath = new \DOMXPath($this->getDesignMap());
        $xpath->registerNamespace("idPkg", "http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging");
        // I just didn't want to type all the loading logic out so I did a complicated loop
        foreach($this->packageElements as $packageElement => $setPrefix) {
            $elements = $xpath->query("//idPkg:$packageElement");
            if($elements->length > 0) {
                foreach($elements as $element) {
                    $filename = $element->getAttribute("src");
                    if(file_exists($this->getDirectory() . $filename)) {
                        $file = $this->createDom($filename);
                        $setter = "$setPrefix$packageElement";
                        $this->$setter($file);
                    }
                }
            }
        }
        return $this;
    }

	/**
	 * Save the designmap.xml file to disk.
	 */
    public function saveDesignMap()
    {
        $this->getDesignMap()->save($this->getDesignMap()->documentURI);
        return $this;
    }

	/**
	 * Save the stories files to disk.
	 */
    public function saveStories()
    {
        $this->saveArrayOfDoms($this->getStories());
        return $this;
    }

	/**
	 * Save the master spreads files to disk.
	 */
    public function saveMasterSpreads()
    {
        $this->saveArrayOfDoms($this->getMasterSpreads());
        return $this;
    }

	/**
	 * Save the spreads files to disk.
	 */
    public function saveSpreads()
    {
        $this->saveArrayOfDoms($this->getSpreads());
        return $this;
    }

    /**
     * Saves all of the individual documents in the IDML package to their documentURI locations.
     */
    public function saveAll()
    {
        $this->saveDesignMap()
            ->saveStories()
            ->saveMasterSpreads()
            ->saveSpreads();
        foreach($this->packageElements as $element => $setPrefix) {
            if($setPrefix == "set") {
                $getter = "get$element";
                $file = $this->$getter();
                if($file instanceof \DOMDocument) {
                    $file->save($file->documentURI);
                }
            }
        }
        if($this->isZip()) {
            $this->zipPackage($this->getZip());
        }
        return $this;
    }

    /**
     * Zip this package into an IDML file from its component parts.
     * @param string $name [optional] If supplied, this is the filename of the zipped package. If not supplied,
     * this defaults to the name of the directory of this IDML package with a ".idml" extension.
     */
    public function zipPackage($name = "")
    {
        $currentDirectory = getcwd();
        chdir($this->getDirectory());
        if($name == "") {
            if(empty($this->getZip()) == false) {
                $name = basename($this->getZip());
            } else {
                $name = basename($this->getDirectory()) . ".idml";
            }
        }
        shell_exec("zip -r -9 $name *");
        $this->setZip($name);
        chdir($currentDirectory);
        return $this;
    }

    /**
     * Adds a story to the story array of this IDML package.
     * @param \DOMDocument $val The story to add.
     */
    public function addStory(\DOMDocument $val)
    {
        $key = str_replace(["Story_", ".xml"], "", basename($val->documentURI));
        $this->stories[$key] = $val;
        return $this;
    }

    /**
     * Adds a story to the designmap of the IDML package so InDesign knows it is there.
     * @param \DOMDocument $val The story to add to the designmap.
     */
    public function addStoryToDesignMap(\DOMDocument $val)
    {
        $this->addStory($val);
        $node = $this->getDesignMap()->createElement("idPkg:Story");
        $this->getDesignMap()->documentElement->appendChild($node);
        $source = str_replace($this->getDirectory(), "", $val->documentURI);
        $node->setAttribute("src", $source);
        return $this;
    }

	/**
	 * Add a spread to the spreads property of this package.
	 * Does NOT do anything other than this - no file creation/saving/etc.
	 */
    public function addSpread(\DOMDocument $val)
    {
        $this->spreads[$this->getSelfAttributeOfDom($val)] = $val;
        return $this;
    }

    /**
     * Adds an element to the spread of this IDML package.
     * See the notes on the getSpread() method - you'll have to adjust this if your IDML
     * files have more than one spread.
     * @param \DOMNode $val The element to be added to the spread.
     */
    public function addElementToSpread(\DOMNode $val)
    {
        $spreadNodelist = $this->getSpread()->getElementsByTagName("Spread");
        $spreadElement = $spreadNodelist->item($spreadNodelist->length - 1);
        $spreadElement->appendChild($this->getSpread()->importNode($val, true));
        return $this;
    }

	/**
	 * Add a master spread to the master spreads property of this package.
	 * Does NOT do anything other than this - no file creation/saving/etc.
	 */
    public function addMasterSpread(\DOMDocument $val)
    {
        $this->masterSpreads[$this->getSelfAttributeOfDom($val)] = $val;
        return $this;
    }

    /**
     * This is a convenience method for the project that spawned this class. All of the IDML
     * files used in that project have only one spread so this was easier than typing it out
     * in a thousand different places. Don't use if your IDML files have multiple spreads.
     * @return \DOMDocument The spread.
     */
    public function getSpread()
    {
        return array_values($this->getSpreads())[0];
    }

    /**
     * Get layers from the designmap.xml of this IDML package.
     * @param bool $selfsOnly If true, returns only the self attributes of the layers. If false,
     * returns the layer elements.
     * @param bool $visibleOnly If true, returns only visible layers. If false, returns all layers.
     * @return mixed If $selfsOnly is true, this is an array of strings. Otherwise, it is a
     * \DOMNodeList of the layer elements.
     */
    public function getLayers($selfsOnly = false, $visibleOnly = true)
    {
        $xpath = new \DOMXPath($this->getDesignMap);
        if($visibleOnly) {
            $query = "//Layer[@Visible='true']";
        } else {
            $query = "//Layer";
        }
        $layers = $xpath->query($query);
        if($selfsOnly) {
            $names = [];
            foreach($layers as $layer) {
                $names[] = $layer->getAttribute("Self");
            }
            sort($names);
            return array_unique($names);
        }
        return $layers;
    }

    /**
     * Returns the specified DOM element if it can be found within the package.
     * @param string $self The self attibute of the requested DOM element. Generally something like "u12f".
     * @return mixed A \DOMNode of the requested element if it is found, or false if it is not.
     */
    public function getElementBySelfAttribute($self = "")
    {
        if($self !== "") {
            $doms = array_merge(
                $this->getSpreads(),
                $this->getMasterSpreads(),
                $this->getStories(),
                [$this->getBackingStory()]
            );
            foreach($doms as $dom) {
                $xpath = new \DOMXPath($dom);
                $elements = $xpath->query("//node()[@Self='$self']");
                if($elements->length > 0) {
                    return $elements->item(0);
                }
            }
        }
        return false;
    }

    /**
     * Determine whether this IDML package was loaded from a zipped .idml package, or from an unzipped directory.
     * @return bool True means it was created from a zip. False means it was created from a directory.
     */
    protected function isZip()
    {
        if(empty($this->getZip()) == false && basename($this->getDirectory())[0] == ".") {
            return true;
        }
        return false;
    }

    /**
     * Create a \DOMDocument and load it with the supplied file.
     * @param string $filename [optional] The name of the file to be loaded.
     * @return \DOMDocument The object that was created.
     */
    protected function createDom($filename = "")
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $tryFiles = [$filename, $this->getDirectory() . $filename];
        if($filename != "") {
            foreach($tryFiles as $file) {
                if(file_exists($file)) {
                    $dom->load($file);
                }
            }
        }
        return $dom;
    }

    /**
     * Saves each \DOMDocument in an array to disk, using its documentURI property as the file location.
     * @param array $array An array of \DOMDocuments.
     */
    protected function saveArrayOfDoms(array $array)
    {
        foreach($array as $dom) {
            $dom->save($dom->documentURI);
        }
        return $this;
    }
   
	/**
	 * A convenience method to get the self attribute of the main element of a component.
	 * e.g. for a story, this returns u123 from <Story Self="u123">
	 */
    protected function getSelfAttributeOfDom(\DOMDocument $dom)
    {
        $elementName = str_replace("idPkg:", "", $dom->documentElement->nodeName);
        $element = $dom->documentElement->getElementsByTagName($elementName)->item(0);
        return $element->getAttribute("Self");
    }
}
