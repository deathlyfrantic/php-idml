<?php
// Copyright © 2016-2017 Zandr Martin

// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and associated documentation files (the "Software"),
// to deal in the Software without restriction, including without limitation
// the rights to use, copy, modify, merge, publish, distribute, sublicense,
// and/or sell copies of the Software, and to permit persons to whom the
// Software is furnished to do so, subject to the following conditions:

// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.

// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
// EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
// OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
// IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
// TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
// OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

namespace IDML;

use \DOMDocument;
use \DOMNodeList;
use \DOMXPath;
use \DOMNode;
use \DOMElement;
use \RecursiveDirectoryIterator;
use \FilesystemIterator;
use \UnexpectedValueException;
use \ZipArchive;
use \SplFileInfo;
use IDML\Exception\Error;

/**
 * An object that handles all of the individual files in an IDML package.
 * This is a very simple class that was developed as part of a much larger
 * project. It basically only serves as a file manager/server for the component
 * XML files of an IDML package.
 *
 * Any methods that aren't specifically getters should return $this to facilitate
 * method chaining.
 */
class Package {

    const IDML_FILENAME_EXTENSION = ".idml";
    const IDML_NAMESPACE_PREFIX = "idPkg";
    const IDML_NAMESPACE_URI = "http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging";

    /**
     * @var DOMDocument
     */
    protected $designMap;

    /**
     * @var DOMElement[]
     */
    protected $masterSpreads = [];

    /**
     * @var DOMDocument
     */
    protected $graphic;

    /**
     * @var DOMDocument
     */
    protected $fonts;

    /**
     * @var DOMDocument
     */
    protected $styles;

    /**
     * @var DOMDocument
     */
    protected $preferences;

    /**
     * @var DOMElement[]
     */
    protected $spreads = [];

    /**
     * @var DOMElement[]
     */
    protected $stories = [];

    /**
     * @var DOMDocument
     */
    protected $backingStory;

    /**
     * @var DOMDocument
     */
    protected $tags;

    /**
     * @var DOMDocument
     */
    protected $mapping;

    /**
     * @var string
     */
    protected $directory = '';

    /**
     * @var string
     */
    protected $zip = '';

    /**
     * @var string
     */
    protected $unzip_path = '';

    /**
     * @return string
     */
    public function getUnzipPath()
    {
        if (!$this->unzip_path && $this->zip) {
            $this->unzip_path = dirname($this->zip) . DIRECTORY_SEPARATOR . "." . basename($this->zip);
        }
        return $this->unzip_path;
    }

    /**
     * @param string $unzip_path
     * @return $this
     */
    public function setUnzipPath($unzip_path)
    {
        $this->unzip_path = rtrim($unzip_path, '/').'/';

        return $this;
    }

    /**
     * Return the full complete paths of the entire contents of a directory including all subdirectories.
     * @param string $path The path of the directory whose contents you want.
     * @return array An array of the full paths of those contents.
     */
    // this is included here to make this class standalone but ideally you'd factor it out
    // see my gist of recursive utils @ https://gist.github.com/zandrmartin/086bba7b2a25ec8e57cc
    public function getDirectoryContents($path) {
        $results = [];

        try {
            $iterator = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $i) {
                $results[] = $i;
                if ($i->isDir()) {
                    $results = array_merge($results, $this->getDirectoryContents($i));
                }
            }
        } catch (UnexpectedValueException $e) {
            // $results is already an empty array so nothing to do here, we'll just return it as is.
        }

        return $results;
    }

    /**
     * This is an array used to map package elements to their respective load methods.
     * See $this->load(). I am lazy and hate typing.
     *
     * @var array
     */
    protected $packageElements = [
        "BackingStory" => "set",
        "Fonts"        => "set",
        "Graphic"      => "set",
        "Mapping"      => "set",
        "MasterSpread" => "add",
        "Preferences"  => "set",
        "Spread"       => "add",
        "Story"        => "add",
        "Styles"       => "set",
        "Tags"         => "set"
    ];

    /**
     * If the parameter is an IDML file - and there is not a ton of checking that goes into that -
     * pass it to setZip() which unzips it and loads up stuff. Otherwise, if it's a directory,
     * load it from that.
     * The project I developed this for uses IDML packages that live in an unzipped form
     * so they can be easily-modified before zipping up for processing.
     *
     * @param string|null $path
     */
    public function __construct($path = null) {
        if ($path) {
            if (preg_match("#".self::IDML_FILENAME_EXTENSION."$#i", $path)) {
                $this
                    ->setZip($path)
                    ->load();
            } else if (is_dir($path)) {
                $this
                    ->setDirectory($path)
                    ->load();
            }
        }
    }

    /**
     * Basically if this package was loaded from an IDML file, we're assuming we unzipped it,
     * so delete the temp directory and all the files we created by unzipping it.
     */
    public function __destruct() {
        if ($this->isZip() && is_dir($this->getDirectory())) {
            $pathObjects = $this->getDirectoryContents($this->getDirectory());

            $contents = array_map(
                function (SplFileInfo $pathObject) {
                    return $pathObject->getRealPath();
                },
                array_reverse($pathObjects)
            );

            foreach ($contents as $content) {
                if (is_dir($content)) {
                    rmdir($content);
                } else {
                    unlink($content);
                }
            }

            rmdir($this->getDirectory());
        }
    }

    /**
     * Get the design map of the IDML.
     * @return DOMDocument The designmap.xml of the IDML package.
     */
    public function getDesignMap() {
        return $this->designMap;
    }

    /**
     * Set the design map of the IDML.
     * @param DOMDocument $val The DOMDocument object loaded with the designmap.xml file.
     * @return $this
     */
    public function setDesignMap(DOMDocument $val) {
        $this->designMap = $val;
        return $this;
    }

    /**
     * Get a single master spread.
     * @param $key
     * @return DOMElement|null
     */
    public function getMasterSpread($key) {
        if (array_key_exists($key, $this->masterSpreads)) {
            return $this->masterSpreads[$key];
        }

        return null;
    }

    /**
     * @return DOMElement[]
     */
    public function getMasterSpreads() {
        return $this->masterSpreads;
    }

    /**
     * Master spreads setter. If you only have one, wrap it in [] before passing.
     * @param array $val The array of master spreads.
     * @return $this This object for method chaining.
     */
    public function setMasterSpreads(array $val) {
        $this->designMap = $val;
        return $this;
    }

    /**
     * Returns the DOMDocument object of the Graphic.xml file.
     * @return DOMDocument
     */
    public function getGraphic() {
        return $this->graphic;
    }

    /**
     * Graphic setter.
     * @param DOMDocument $val The Graphic.xml file's DOMDocument.
     * @return $this This object for method chaining.
     */
    public function setGraphic(DOMDocument $val) {
        $this->graphic = $val;
        return $this;
    }

    /**
     * Returns the DOMDocument object of the Fonts.xml file.
     * @return DOMDocument
     */
    public function getFonts() {
        return $this->fonts;
    }

    /**
     * Fonts setter.
     * @param DOMDocument $val The Fonts.xml file's DOMDocument.
     * @return $this This object for method chaining.
     */
    public function setFonts(DOMDocument $val) {
        $this->fonts = $val;
        return $this;
    }

    /**
     * Returns the DOMDocument object of the Styles.xml file.
     * @return DOMDocument
     */
    public function getStyles() {
        return $this->styles;
    }

    /**
     * Styles setter.
     * @param DOMDocument $val The Styles.xml file's DOMDocument.
     * @return $this This object for method chaining.
     */
    public function setStyles(DOMDocument $val) {
        $this->styles = $val;
        return $this;
    }

    /**
     * Returns the DOMDocument object of the Preferences.xml file.
     * @return DOMDocument
     */
    public function getPreferences() {
        return $this->preferences;
    }

    /**
     * Preferences setter.
     * @param DOMDocument $val The Preferences.xml file's DOMDocument.
     * @return $this This object for method chaining.
     */
    public function setPreferences(DOMDocument $val) {
        $this->preferences = $val;
        return $this;
    }

    /**
     * @param $key
     * @return DOMElement|null
     */
    public function getSpread($key) {
        if (array_key_exists($key, $this->spreads)) {
            return $this->spreads[$key];
        }

        return null;
    }

    /**
     * @return DOMElement[]
     */
    public function getSpreads() {
        return $this->spreads;
    }

    /**
     * Spreads setter. If you only have one, wrap it in [] before passing.
     * @param array $val The array of spreads.
     * @return $this This object for method chaining.
     */
    public function setSpreads(array $val) {
        $this->spreads = $val;
        return $this;
    }

    /**
     * @param $key
     * @return DOMElement|null
     */
    public function getStorie($key) {
        if (array_key_exists($key, $this->stories)) {
            return $this->stories[$key];
        }

        return null;
    }

    /**
     * @return DOMElement[]
     */
    public function getStories() {
        return $this->stories;
    }

    /**
     * Stories setter. If you only have one, wrap it in [] before passing.
     * @param array $val The array of stories.
     * @return $this This object for method chaining.
     */
    public function setStories(array $val) {
        $this->stories = $val;
        return $this;
    }

    /**
     * Returns the DOMDocument object of the BackingStory.xml file.
     * @return DOMDocument
     */
    public function getBackingStory() {
        return $this->backingStory;
    }

    /**
     * Backing story setter.
     * @param DOMDocument $val The BackingStory.xml file's DOMDocument.
     * @return $this This object for method chaining.
     */
    public function setBackingStory(DOMDocument $val) {
        $this->backingStory = $val;
        return $this;
    }

    /**
     * Returns the DOMDocument object of the Tags.xml file.
     * @return DOMDocument
     */
    public function getTags() {
        return $this->tags;
    }

    /**
     * Tags setter.
     * @param DOMDocument $val The Tags.xml file's DOMDocument.
     * @return $this This object for method chaining.
     */
    public function setTags(DOMDocument $val) {
        $this->tags = $val;
        return $this;
    }

    /**
     * Returns the DOMDocument object of the Mapping.xml file.
     * @return DOMDocument
     */
    public function getMapping() {
        return $this->mapping;
    }

    /**
     * Mapping setter.
     * @param DOMDocument $val The Mapping.xml file's DOMDocument.
     * @return $this This object for method chaining.
     */
    public function setMapping(DOMDocument $val) {
        $this->mapping = $val;
        return $this;
    }

    /**
     * Set the directory for this IDML package. Basically if you have /directory/idmlfile.idml
     * and unzipped it, you'd setDirectory("/directory/").
     * @param string $path
     * @return $this
     * @throws Error
     */
    public function setDirectory($path) {
        if (is_dir($path)) {
            $this->directory = rtrim(realpath($path), '/').'/';
        } else {
            throw new Error(sprintf('Directory not found : %s', $path));
        }

        return $this;
    }

    /**
     * Returns a string containing the directory name of this IDML.
     * @return string
     */
    public function getDirectory() {
        return $this->directory;
    }

    /**
     * Returns a string containing the zip file name of this IDML.
     * @return string
     */
    public function getZip() {
        return $this->zip;
    }

    /**
     * Set the zip file for this package. If you have /directory/idmlfile.idml, you'd
     * setZip("/directory/idmlfile.idml"). This would then unzip that file to /directory/.idmlfile.idml/
     * and load all the stuff.
     * The __destruct() method ensures this temporary directory will be deleted upon object destruction.
     *
     * @param $path string
     * @return $this
     * @throws Error
     */
    public function setZip($path) {
        if (file_exists($path)) {
            $this->zip = realpath($path);
            return $this;
        } else {
            throw new Error(sprintf('File not found : %s', $path));
        }
    }


    /**
     * @param string $zip_filename
     * @param string $unzip_path
     * @return bool
     * @throws Error
     */
    public function unZip($zip_filename, $unzip_path) {
        mkdir($unzip_path, 0777, true);
        $this->setDirectory($unzip_path);
        $zip = new ZipArchive();
        if ($zip->open($zip_filename)) {
            if (!$zip->extractTo($unzip_path)) {
                throw new Error(sprintf('Unable to extract file : %s', $zip_filename));
            }
            return $zip->close();
        } else {
            throw new Error(sprintf('Unable to open file : %s', $zip_filename));
        }
    }

    /**
     * Set all array properties to empty arrays.
     * @return $this
     */
    public function unsetArrays() {
        $this->setSpreads([])
            ->setMasterSpreads([])
            ->setStories([]);
        return $this;
    }

    /**
     * Special method to load the designmap.xml of this IDML which is required
     * to get all of the other components.
     * @return $this
     */
    public function loadDesignMap() {
        $designmap = $this->createDom($this->getDirectory() . "designmap.xml");
        $this->setDesignMap($designmap);
        return $this;
    }

    /**
     * This is the master load method that will create populate the object with
     * DOMDocuments of the component XML files. You need to set the location of
     * the IDML before this method is called - either by passing it with the
     * instantiation (new IDML\Package("idmlfile.idml")) or by
     * calling the setZip() or setDirectory() methods.
     * @return $this
     */
    public function load() {
        if (!$this->getDirectory()) {
            $this->unZip($this->getZip(), $this->getUnZipPath());
        }

        // since some files are appended to arrays, let's unset those arrays when we load just to be safe
        $this->unsetArrays();

        $this->loadDesignMap();

        $xpath = new DOMXPath($this->getDesignMap());
        $xpath->registerNamespace(self::IDML_NAMESPACE_PREFIX, self::IDML_NAMESPACE_URI);
        // I just didn't want to type all the loading logic out so I did a complicated loop

        foreach ($this->packageElements as $packageElement => $setPrefix) {
            $elements = $xpath->query("//".self::IDML_NAMESPACE_PREFIX.":".$packageElement);

            if ($elements->length) {
                /** @var DOMElement $element */
                foreach ($elements as $element) {
                    $filename = $element->getAttribute("src");
                    $filepath = $this->getDirectory() . $filename;
                    if (file_exists($filepath)) {
                        $file = $this->createDom($filepath);
                        $setter = $setPrefix.$packageElement;
                        $this->$setter($file);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Save the designmap.xml file to disk.
     * @return $this
     */
    public function saveDesignMap() {
        $this->getDesignMap()->save($this->getDesignMap()->documentURI);
        return $this;
    }

    /**
     * Save the stories files to disk.
     * @return $this
     */
    public function saveStories() {
        $this->saveArrayOfDoms($this->getStories());
        return $this;
    }

    /**
     * Save the master spreads files to disk.
     * @return $this
     */
    public function saveMasterSpreads() {
        $this->saveArrayOfDoms($this->getMasterSpreads());
        return $this;
    }

    /**
     * Save the spreads files to disk.
     * @return $this
     */
    public function saveSpreads() {
        $this->saveArrayOfDoms($this->getSpreads());
        return $this;
    }

    /**
     * Saves all of the individual documents in the IDML package to their documentURI locations.
     * @param null|string $zip_file_path
     * @return $this
     */
    public function saveAll($zip_file_path = null) {
        $this->saveDesignMap()
            ->saveStories()
            ->saveMasterSpreads()
            ->saveSpreads();

        foreach ($this->packageElements as $element => $setPrefix) {
            if ($setPrefix == "set") {
                $getter = "get".$element;
                $file = $this->$getter();

                if ($file instanceof DOMDocument) {
                    $file->save($file->documentURI);
                }
            }
        }

        $zip_file_path = $zip_file_path ? $zip_file_path : ($this->isZip() ? $this->getZip() : null);
        if ($zip_file_path) {
            $this->zipPackage($zip_file_path);
        }

        return $this;
    }

    /**
     * Zip this package into an IDML file from its component parts.
     * @param string|null $zip_file_path [optional] If supplied, this is the filename of the zipped package. If not supplied,
     * this defaults to the name of the directory of this IDML package with a ".idml" extension.
     * @return $this
     */
    public function zipPackage($zip_file_path = null) {
        $currentDirectory = getcwd();
        chdir($this->getDirectory());

        if (!$zip_file_path) {
            if ($this->isZip()) {
                $zip_file_path = basename($this->getZip());
            } else {
                $zip_file_path = basename($this->getDirectory()) . self::IDML_FILENAME_EXTENSION;
            }
        }

        $zip = new ZipArchive();
        $zip->open($zip_file_path, ZipArchive::CREATE);
        $dir = new SplFileInfo(".");

        // this is included here to make this class standalone, but ideally
        // you'd factor it out into a class that extends ZipArchive
        $contents = [];
        $baseDir = null;
        if ($dir->isDir()) {
            $contents = $this->getDirectoryContents($this->getDirectory());
            $baseDir = $dir->getPathInfo()->getRealPath();
        }

        if (count($contents) === 0) {
            $zip->addEmptyDir($dir->getBasename());
        } else {
            foreach ($contents as $c) {
                if (is_dir($c)) {
                    // safe to do because directories will always come before their contents
                    // in the array returned by getDirectoryContents()
                    $zip->addEmptyDir($dir->getBasename());
                } else {
                    $zip->addFile($c, str_replace($baseDir . DIRECTORY_SEPARATOR, "", $c));
                }
            }
        }

        $zip->close();
        $this->setZip($zip_file_path);
        chdir($currentDirectory);
        return $this;
    }

    /**
     * Adds a story to the story array of this IDML package.
     * @param DOMDocument $val The story to add.
     * @return $this
     */
    public function addStory(DOMDocument $val) {
        $key = str_replace(["Story_", ".xml"], "", basename($val->documentURI));
        $this->stories[$key] = $val;
        return $this;
    }

    /**
     * Adds a story to the designmap of the IDML package so InDesign knows it is there.
     * @param DOMDocument $val The story to add to the designmap.
     * @return $this
     */
    public function addStoryToDesignMap(DOMDocument $val) {
        $this->addStory($val);
        $node = $this->getDesignMap()->createElement(self::IDML_NAMESPACE_PREFIX.":Story");
        $this->getDesignMap()->documentElement->appendChild($node);
        $source = str_replace($this->getDirectory(), "", $val->documentURI);
        $node->setAttribute("src", $source);
        return $this;
    }

    /**
     * Add a spread to the spreads property of this package.
     * Does NOT do anything other than this - no file creation/saving/etc.
     * @param DOMDocument $val
     * @return $this
     */
    public function addSpread(DOMDocument $val) {
        $this->spreads[$this->getSelfAttributeOfDom($val)] = $val;
        return $this;
    }

    /**
     * Adds an element to the spread of this IDML package.
     * See the notes on the getFirstSpread() method - you'll have to adjust this if your IDML
     * files have more than one spread.
     * @param DOMNode $val The element to be added to the spread.
     * @param DOMDocument $spread The spread you want to which you want to add the element. If not provided, defaults
     * to whatever getFirstSpread() returns.
     * @return $this
     */
    public function addElementToSpread(DOMNode $val, DOMDocument $spread = null) {
        if (!$spread) {
            $spread = $this->getFirstSpread();
        }

        $spreadNodelist = $spread->getElementsByTagName("Spread");
        $spreadElement = $spreadNodelist->item($spreadNodelist->length - 1);
        $spreadElement->appendChild($spread->importNode($val, true));
        return $this;
    }

    /**
     * Add a master spread to the master spreads property of this package.
     * Does NOT do anything other than this - no file creation/saving/etc.
     * @param DOMDocument $val
     * @return $this
     */
    public function addMasterSpread(DOMDocument $val) {
        $this->masterSpreads[$this->getSelfAttributeOfDom($val)] = $val;
        return $this;
    }

    /**
     * This is a convenience method for the project that spawned this class. All of the IDML
     * files used in that project have only one spread so this was easier than typing it out
     * in a thousand different places. Don't use if your IDML files have multiple spreads.
     * @return DOMDocument The spread.
     */
    public function getFirstSpread() {
        return array_values($this->getSpreads())[0];
    }

    /**
     * Get layers from the designmap.xml of this IDML package.
     * @param bool $selfsOnly If true, returns only the self attributes of the layers. If false,
     * returns the layer elements.
     * @param bool $visibleOnly If true, returns only visible layers. If false, returns all layers.
     * @return string[]|DOMNodeList If $selfsOnly is true, this is an array of strings. Otherwise, it is a
     * DOMNodeList of the layer elements.
     */
    public function getLayers($selfsOnly = false, $visibleOnly = true) {
        $xpath = new DOMXPath($this->getDesignMap());
        $query = ($visibleOnly) ? "//Layer[@Visible='true']" : "//Layer";
        $layers = $xpath->query($query);

        if ($selfsOnly) {
            $names = array_map(
                function (DOMElement $layer) {
                    return $layer->getAttribute("Self");
                },
                (array) $layers
            );

            sort($names);
            return array_unique($names);
        }

        return $layers;
    }

    /**
     * Returns the specified DOM element if it can be found within the package.
     * @param string $self The self attibute of the requested DOM element. Generally something like "u12f".
     * @throws Error if the specified node cannot be found.
     * @return DOMNode A DOMNode of the requested element if it is found.
     */
    public function getElementBySelfAttribute($self = "") {
        if ($self !== "") {
            $doms = array_merge(
                $this->getSpreads(),
                $this->getMasterSpreads(),
                $this->getStories(),
                [$this->getBackingStory()]
            );

            foreach ($doms as $dom) {
                $xpath = new DOMXPath($dom);
                $elements = $xpath->query("//node()[@Self='".$self."']");

                if ($elements->length > 0) {
                    return $elements->item(0);
                }
            }
        }

        throw new Error(sprintf("Unable to find DOM node with self attribute %s.", $self));
    }

    /**
     * Determine whether this IDML package was loaded from a zipped .idml package, or from an unzipped directory.
     * @return bool True means it was created from a zip. False means it was created from a directory.
     */
    protected function isZip() {
        return ($this->getZip() && basename($this->getDirectory())[0] === ".");
    }

    /**
     * Create a DOMDocument and load it with the supplied file.
     * @param string $filename The name of the file to be loaded.
     * @return DOMDocument The object that was created.
     * @throws Error
     */
    protected function createDom($filename) {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if ($filename && file_exists($filename)) {
            $dom->load($filename);
            return $dom;
        } else {
            throw new Error(sprintf("Unable to load file : %s.", $filename));
        }
    }

    /**
     * Saves each DOMDocument in an array to disk, using its documentURI property as the file location.
     * @param array $array An array of DOMDocuments.
     * @return $this
     */
    protected function saveArrayOfDoms(array $array) {
        foreach ($array as $dom) {
            $dom->save($dom->documentURI);
        }

        return $this;
    }

    /**
     * A convenience method to get the self attribute of the main element of a component.
     * e.g. for a story, this returns u123 from <Story Self="u123">
     * @param DOMDocument $dom
     * @return string
     */
    protected function getSelfAttributeOfDom(DOMDocument $dom) {
        $elementName = str_replace(self::IDML_NAMESPACE_PREFIX.":", "", $dom->documentElement->nodeName);
        $element = $dom->documentElement->getElementsByTagName($elementName)->item(0);
        return $element->getAttribute("Self");
    }

    /**
     * Returns the ParagraphStyle or CharacterStyle node from the Styles.xml file that is
     * assocated with the given $node.
     * @param DOMElement $node The node whose AppliedStyle you want.
     * @throws Error if the style node you want could not be found.
     * @return DOMElement Either the DOMNode of the applied style you want.
     */
    public function getAppliedStyle(DOMElement $node) {
        $xpath = new DOMXPath($this->getStyles());
        $nodeType = str_replace("StyleRange", "", $node->nodeName);
        $type = "Applied{".$nodeType."}Style";
        $style = $node->getAttribute($type);

        if (!$style) {
            throw new Error("Unable to find style node for given {$node->nodeName}.");
        }

        $nodeList = $xpath->query("//node()[@Self='".$style."']");

        if ($nodeList->length > 0) {
           return $nodeList->item(0);
        }

        throw new Error(sprintf("Unable to find style node for given {%s}.", $node->nodeName));
    }

    /**
     * Searches for a given style attribute as exhaustively as possible.
     * @param DOMElement $node The node whose attribute you want.
     * @param string $attr The name of the attribute you want.
     * @throws Error if attribute could not be found.
     * @return string Either a string of the attribute you want.
     */
    public function getStyleAttribute(DOMElement $node, $attr) {
        $parent = $node->parentNode;
        $appliedCSR = $this->getAppliedStyle($node);
        $appliedPSR = $this->getAppliedStyle($parent);

        // order of elements here is intentional
        foreach ([$node, $appliedCSR, $parent, $appliedPSR] as $element) {
            $ret = $element->getAttribute($attr);
            if ($ret) {
                return $ret;
            }
        }

        throw new Error(sprintf("Unable to find value for attribute %s.", $attr));
    }

    /**
     * Searches for a given style property as exhaustively as possible.
     * @param DOMElement $node The node whose property you want.
     * @param string $prop The name of the property you want.
     * @throws Error if property could not be found.
     * @return string Either a string of the property you want.
     */
    public function getStyleProperty(DOMElement $node, $prop) {
        $parent = $node->parentNode;
        $appliedCSR = $this->getAppliedStyle($node);
        $appliedPSR = $this->getAppliedStyle($parent);
        $propertyGroups = [];

        // order of elements here is intentional
        /** @var DOMElement $element */
        foreach ([$node, $appliedCSR, $parent, $appliedPSR] as $element) {
            $p = $element->getElementsByTagName("Properties");
            if ($p->length > 0) {
                $propertyGroups[] = $p->item(0);
            }
        }

        /** @var DOMElement $group */
        foreach ($propertyGroups as $group) {
            $propList = $group->getElementsByTagName($prop);
            if ($propList->length > 0) {
                return $propList->item(0)->nodeValue;
            }
        }

        throw new Error(sprintf("Unable to find value for property %s.", $prop));
    }

    /**
     * Get the tag of an element from the XML/BackingStory.xml file.
     * @param DOMElement $node The element for which we want the tag.
     * @throws Error if the tag could not be found.
     * @return string The tag if one is found.
     */
    public function getMarkupTag(DOMElement $node) {
        $tag = false;
        $selfs = [$node->getAttribute("Self")];
        $xpath = new DOMXPath($this->getBackingStory());

        if ($node->nodeName === "TextFrame") {
            $selfs[] = $node->getAttribute("ParentStory");
        }

        foreach ($selfs as $self) {
            $xmlElement = false;

            while ($node->parentNode) {
                /** @var DOMElement $node */
                $node = $node->parentNode;
                if ("XMLElement" === $node->nodeName) {
                    $xmlElement = $node;
                    break;
                }
            }

            if ($xmlElement === false) {
                $xmlElements = $xpath->query("//XMLElement[@XMLContent='".$self."']");
                if ($xmlElements->length > 0) {
                    $xmlElement = $xmlElements->item(0);
                }
            }

            if ($xmlElement && $xmlElement->getAttribute("MarkupTag")) {
                $tag = str_replace("XMLTag/", "", $xmlElement->getAttribute("MarkupTag"));
            }
        }

        if ($tag !== false) {
            return urldecode($tag);
        }

        throw new Error(sprintf("Unable to find markup tag for given {%s} node.", $node->nodeName));
    }
}
