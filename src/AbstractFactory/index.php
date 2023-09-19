<?php

namespace PerficientTest\AbstractFactory;

/**
 * La interfaz Abstract Factory declara métodos de creación para cada 
 * tipo de producto distinto.
 */
interface TemplateFactory
{
    public function createTitleTemplate(): TitleTemplate;

    public function createPageTemplate(): PageTemplate;

    public function getRenderer(): TemplateRenderer;
}

/**
 * Cada Concrete Factory corresponde a una variante (o familia) 
 * específica de productos.
 * 
 * Esta Concrete Factory crea plantillas Twig.
 */
class TwigTemplateFactory implements TemplateFactory
{
    public function createTitleTemplate(): TitleTemplate
    {
        return new TwigTitleTemplate();
    }

    public function createPageTemplate(): PageTemplate
    {
        return new TwigPageTemplate($this->createTitleTemplate());
    }

    public function getRenderer(): TemplateRenderer
    {
        return new TwigRenderer();
    }
}

/**
 * Y esta Concrete Factory crea plantillas PHPTemplate.
 */
class PHPTemplateFactory implements TemplateFactory
{
    public function createTitleTemplate(): TitleTemplate
    {
        return new PHPTemplateTitleTemplate();
    }

    public function createPageTemplate(): PageTemplate
    {
        return new PHPTemplatePageTemplate($this->createTitleTemplate());
    }

    public function getRenderer(): TemplateRenderer
    {
        return new PHPTemplateRenderer();
    }
}

/**
 * Cada tipo de producto distinto debe tener una interfaz separada.
 * Todas las variantes del producto deben seguir la mimsa intefaz.
 * 
 * Por ejemplo, esta interfaz de Abstract Product describe el
 * comportamiento de las plantillas de títulos de página.
 */
interface TitleTemplate
{
    public function getTemplateString(): string;
}

/**
 * Este Concrete Product proporciona plantillas de títulos de
 * páginas de Twig.
 */
class TwigTitleTemplate implements TitleTemplate
{
    public function getTemplateString(): string
    {
        return "<h1>{{ title }}</h1>";
    }
}

/**
 * Y este Concrete Product proporciona plantillas de títulos de
 * páginas PHPTemplate.
 */
class PHPTemplateTitleTemplate implements TitleTemplate
{
    public function getTemplateString(): string
    {
        return "<h1><?= \$title; ?></h1>";
    }
}

/**
 * Este es otro tipo de Abstract Product, que describe plantillas
 * de página completa.
 */
interface PageTemplate
{
    public function getTemplateString(): string;
}

/**
 * La plantilla de página utiliza la subplantilla de título, por lo
 * que debemos proporcionar la forma de configurarla en el objeto de
 * subplantilla. La Abstract Factory vinculará la plantilla de página
 * con una plantilla de la misma variante.
 */
abstract class BasePageTemplate implements PageTemplate
{
    protected $titleTemplate;

    public function __construct(TitleTemplate $titleTemplate)
    {
        $this->titleTemplate = $titleTemplate;
    }
}

/**
 * La variante Twig de las plantillas de página completa.
 */
class TwigPageTemplate extends BasePageTemplate
{
    public function getTemplateString(): string
    {
        $renderedTitle = $this->titleTemplate->getTemplateString();

        return <<<HTML
        <div class="page">
            $renderedTitle
            <article class="content">{{ content }}</article>
        </div>
        HTML;
    }
}

/**
 * La variante PHPTemplate de las plantillas de página completa.
 */
class PHPTemplatePageTemplate extends BasePageTemplate
{
    public function getTemplateString(): string
    {
        $renderedTitle = $this->titleTemplate->getTemplateString();

        return <<<HTML
        <div class="page">
            $renderedTitle
            <article class="content"><?= \$content; ?></article>
        </div>
        HTML;
    }
}

/**
 * El renderizador es responsable de convertir una cadena de plantilla en el 
 * código HTML real. Cada renderizador se comporta de manera diferente y espera
 * que se le pase su propio tipo de cadenas de plantilla. Las plantillas horneadas
 * con la fábrica le permiten pasar los tipos adecuados de plantillas a los
 * renderizados adecuados.
 */
interface TemplateRenderer
{
    public function render(string $templateString, array $arguments = []): string;
}

/**
 * El renderizador de plantillas Twig.
 */
class TwigRenderer implements TemplateRenderer
{

    public function render(string $templateString, array $arguments = []): string
    {
        return \Twig::render($templateString, $arguments);
    }
}

/**
 * El renderizador de plantillas PHPTemplate. Tenga en cuenta que esta 
 * implenetación es muy básica, si no tosca. El uso de la función `eval` tiene
 * muchas implicaciones de seguridad, así que úsela con precaución de proyectos
 * reales.
 */
class PHPTemplateRenderer implements TemplateRenderer
{
    public function render(string $templateString, array $arguments = []): string
    {
        extract($arguments);

        ob_start();
        eval(' ?>' . $templateString . '<?php ');
        $result = ob_get_contents();
        ob_end_clean();

        return $result;
    }
}

/**
 * El código cliente. Tenga en cuenta que acepta la clase Abstract Factory como
 * parámetro, lo que permite al cliente trabajar con cualquier tipo de Concrete
 * Factory.
 */
class Page
{
    public $title;

    public $content;

    public function __construct($title, $content)
    {
        $this->title = $title;
        $this->content = $content;
    }

    // Así es como usarías la plantilla en la vida real. Tengan en cuenta que
    // la clase página no depende de ninguna clase de plantilla concreta.  
    public function render(TemplateFactory $factory): string
    {
        $pageTemplate = $factory->createPageTemplate();

        $renderer = $factory->getRenderer();

        return $renderer->render($pageTemplate->getTemplateString(), [
            'title' => $this->title,
            'content' => $this->content
        ]);
    }
}

/**
 * Ahora, en otras partes de la aplicación, el código del cliente puede 
 * aceptar objetos de fábrica de cualquier tipo.
 */
$page = new Page('Sample page', 'This is the body.');

echo "Testing actual rendering with the PHPTemplate factory:\n";
echo $page->render(new PHPTemplateFactory());

//echo "Testing rendering with the Twig factory:\n";
//echo $page->render(new TwigTemplateFactory());
