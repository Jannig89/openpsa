<?php
/**
 * @package midcom.services
 * @author The Midgard Project, http://www.midgard-project.org
 * @copyright The Midgard Project, http://www.midgard-project.org
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

/**
 * This is a class geared at indexing attachments. It requires you to "assign" the
 * attachment to a topic, which is used as TOPIC_URL for permission purposes. In addition
 * you may set another MidgardObject as source object, its GUID is stored in the
 * __SOURCE field of the index.
 *
 * The documents type is "midcom_attachment", though it is *not* derived from midcom
 * for several reasons directly. They should be compatible though, in terms of usage.
 *
 * <b>Example Usage:</b>
 *
 * <code>
 * $document = new midcom_services_indexer_document_attachment($attachment, $object);
 * $indexer->index($document);
 * </code>
 *
 * Where $attachment is the attachment to be indexed and $object is the object the object
 * is associated with. The corresponding topic will be detected using the object's GUID
 * through NAP. If this fails, you have to set the members $topic_guid, $topic_url and
 * $component manually.
 *
 * @todo More DBA stuff: use DBA classes, which allow you to implicitly load the parent
 *     object using get_parent.
 *
 * @package midcom.services
 * @see midcom_services_indexer
 * @see midcom_helper_metadata
 */
class midcom_services_indexer_document_attachment extends midcom_services_indexer_document
{
    private $attachment;

    /**
     * Create a new attachment document
     *
     * @param MidgardAttachment $attachment The Attachment to index.
     * @param MidgardObject $object The source object to which the attachment is bound.
     */
    public function __construct($attachment, $object)
    {
        //before doing anything else, verify that the attachment is readable, otherwise we might get stuck in endless loops later on
        if (!$attachment->open('r')) {
            debug_add('Attachment ' . $attachment->guid . ' cannot be read, aborting. Last midgard error: ' . midcom_connection::get_error_string(), MIDCOM_LOG_ERROR);
            return false;
        }
        $attachment->close();

        parent::__construct();

        $this->_set_type('midcom_attachment');

        $this->attachment = $attachment;

        debug_print_r("Processing this attachment:", $attachment);

        $this->source = $object->guid;
        $this->RI = $attachment->guid;
        $this->document_url = midcom::get()->permalinks->create_attachment_link($this->RI, $attachment->name);

        $this->process_attachment();
        $this->process_topic();
    }

    private function process_attachment()
    {
        if (   !isset($this->attachment->metadata)
            || !is_object($this->attachment->metadata)) {
            return;
        }
        $this->creator = new midcom_db_person($this->attachment->metadata->creator);
        $this->created = $this->attachment->metadata->created;
        $this->editor = $this->creator;
        $this->edited = $this->created;
        $this->author = $this->creator->name;
        $this->add_text('mimetype', $this->attachment->mimetype);
        $this->add_text('filename', $this->attachment->name);

        $mimetype = explode("/", $this->attachment->mimetype);
        debug_print_r("Evaluating this Mime Type:", $mimetype);

        switch ($mimetype[1]) {
            case 'html':
            case 'xml':
                $this->process_mime_html();
                break;

            case 'rtf':
            case 'richtext':
                $this->process_mime_richtext();
                break;

            case 'xml-dtd':
                $this->process_mime_plaintext();
                break;

            case 'pdf':
                $this->process_mime_pdf();
                break;

            case 'msword':
            case 'vnd.ms-word':
                $this->process_mime_word();
                break;

            default:
                if ($mimetype[0] === 'text') {
                    $this->process_mime_plaintext();
                } else {
                    $this->process_mime_binary();
                }
                break;
        }

        if (strlen(trim($this->attachment->title)) > 0) {
            $this->title =  "{$this->attachment->title} ({$this->attachment->name})";
            $this->content .= "\n{$this->attachment->title}\n{$this->attachment->name}";
        } else {
            $this->title =  $this->attachment->name;
            $this->content .= "\n{$this->attachment->name}";
        }

        if (strlen($this->content) > 200) {
            $this->abstract = substr($this->content, 0, 200) . ' ...';
        } else {
            $this->abstract = $this->content;
        }
    }

    /**
     * Convert a Word attachment to plain text and index it.
     */
    private function process_mime_word()
    {
        if (!midcom::get()->config->get('utility_catdoc')) {
            debug_add('Could not find catdoc, indexing as binary.', MIDCOM_LOG_INFO);
            $this->process_mime_binary();
            return;
        }

        debug_add("Converting Word-Attachment to plain text");
        $wordfile = $this->write_attachment_tmpfile();
        $txtfile = "{$wordfile}.txt";
        $encoding = (strtoupper($this->_i18n->get_current_charset()) == 'UTF-8') ? 'utf-8' : '8859-1';

        $command = midcom::get()->config->get('utility_catdoc') . " -d{$encoding} -a $wordfile > $txtfile";
        debug_add("Executing: {$command}");
        exec($command, $result, $returncode);
        debug_print_r("Execution returned {$returncode}: ", $result);

        unlink($wordfile);

        if (!file_exists($txtfile)) {
            // We were unable to read the document into text
            $this->process_mime_binary();
            return;
        }

        $handle = fopen($txtfile, "r");
        $this->content = $this->get_attachment_content($handle);
        // Kill all ^L (FF) characters
        $this->content = str_replace("\x0C", '', $this->content);
        fclose($handle);

        unlink($txtfile);
    }

    /**
     * Convert a PDF attachment to plain text and index it.
     */
    private function process_mime_pdf()
    {
        if (!midcom::get()->config->get('utility_pdftotext')) {
            debug_add('Could not find pdftotext, indexing as binary.', MIDCOM_LOG_INFO);
            $this->process_mime_binary();
            return;
        }

        debug_add("Converting PDF-Attachment to plain text");
        $pdffile = $this->write_attachment_tmpfile();
        $txtfile = "{$pdffile}.txt";
        $encoding = (strtoupper($this->_i18n->get_current_charset()) == 'UTF-8') ? 'UTF-8' : 'Latin1';

        $command = midcom::get()->config->get('utility_pdftotext') . " -enc {$encoding} -nopgbrk -eol unix $pdffile $txtfile 2>&1";
        debug_add("Executing: {$command}");
        exec($command, $result, $returncode);
        debug_print_r("Execution returned {$returncode}: ", $result);

        unlink($pdffile);

        if (!file_exists($txtfile)) {
            // We were unable to read the document into text
            $this->process_mime_binary();
            return;
        }

        $handle = fopen($txtfile, 'r');
        $this->content = $this->get_attachment_content($handle);
        fclose($handle);

        unlink($txtfile);
    }

    /**
     * Convert an RTF attachment to plain text and index it.
     */
    private function process_mime_richtext()
    {
        if (!midcom::get()->config->get('utility_unrtf')) {
            debug_add('Could not find unrtf, indexing as binary.', MIDCOM_LOG_INFO);
            $this->process_mime_binary();
            return;
        }

        debug_add("Converting RTF-Attachment to plain text");
        $rtffile = $this->write_attachment_tmpfile();
        $txtfile = "{$rtffile}.txt";

        // Kill the first five lines, they are crap from the converter.
        $command = midcom::get()->config->get('utility_unrtf') . " --nopict --text $rtffile | sed '1,5d' > $txtfile";
        debug_add("Executing: {$command}");
        exec($command, $result, $returncode);
        debug_print_r("Execution returned {$returncode}: ", $result);

        unlink($rtffile);

        if (!file_exists($txtfile)) {
            // We were unable to read the document into text
            $this->process_mime_binary();
            return;
        }

        $handle = fopen($txtfile, 'r');
        $this->content = $this->_i18n->convert_to_current_charset($this->get_attachment_content($handle));
        fclose($handle);

        unlink($txtfile);
    }

    /**
     * Simple plain-text driver, just copies the attachment.
     */
    private function process_mime_plaintext()
    {
        $this->content = $this->_i18n->convert_to_current_charset($this->get_attachment_content());
    }

    /**
     * Processes HTML-style attachments (should therefore work with XML too),
     * strips tags and resolves entities.
     */
    private function process_mime_html()
    {
        $this->content = $this->_i18n->convert_to_current_charset($this->html2text($this->get_attachment_content()));
    }

    /**
     * Any binary file will have its name in the abstract unless no title
     * is defined, in which case the documents title already contains the file's
     * name.
     */
    private function process_mime_binary()
    {
        if (strlen(trim($this->title)) > 0) {
            $this->abstract = $this->attachment->name;
        }
    }

    /**
     * Returns the first four megabytes of the File referenced by $handle.
     * The limit is in place to
     * avoid clashes with the PHP Memory limit, it should be enough for most text
     * based attachments anyway.
     *
     * If you omit $handle, a handle to the documents' attachment is created. If no
     * handle is specified, it is automatically closed after reading the data, otherwise
     * you have to close it yourselves afterwards.
     *
     * @param resource $handle A valid file-handle to read from, or null to automatically create a
     *        handle to the current attachment.
     */
    private function get_attachment_content($handle = null)
    {
        // Read a max of 4 MB
        debug_add("Returning File content of handle {$handle}");
        $max = 4194304;
        $close = false;
        if (is_null($handle)) {
            $handle = $this->attachment->open('r');
            $close = true;
        }
        $content = fread($handle, $max);
        if ($close) {
            fclose($handle);
        }
        return $content;
    }

    /**
     * Creates a temporary copy of the attachment, the callee must delete it manually
     * after completing procesing.
     *
     * @return string The name of the temporary file.
     */
    private function write_attachment_tmpfile()
    {
        $tmpname = tempnam(midcom::get()->config->get('midcom_tempdir'), 'midcom-indexer');
        debug_add("Creating an attachment copy as {$tmpname}");

        $in = $this->attachment->open('r');
        $out = fopen($tmpname, 'w');
        stream_copy_to_stream($in, $out);
        fclose($out);
        fclose($in);
        return $tmpname;
    }
}
