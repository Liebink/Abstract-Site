<?php

declare(strict_types=1);

namespace LesAbstractSite\Controller;

use Slim\Views\Twig;
use Slim\Psr7\Request;
use Twig\Error\SyntaxError;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use LesAbstractSite\Model\Platform;
use Psr\Http\Message\ResponseInterface;
use LesDomain\Identifier\Generator\Uuid7IdentifierGenerator;
use LesDatabase\Query\Builder\Applier\Values\InsertValuesApplier;

final class ContactController
{
    /**
     * @psalm-pure
     */
    public function __construct(
        private readonly Connection $db,
        private readonly Twig $view,
        private readonly Platform $platform,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function __invoke(Request $request, ResponseInterface $response): ResponseInterface
    {
        $state = 'pending';
        $posted = [];
        $errors = null;

        if ($request->getMethod() === 'POST' && str_contains($request->getHeaderLine('Sec-Fetch-Site'), 'same')) {
            $state = 'posted';
            $posted = $request->getParsedBody();

            if (!is_array($posted)) {
                $posted = [];
            }

            $errors = $this->validate($posted);

            if (count($errors) === 0) {
                $server = $request->getServerParams();
                $msTimestamp = round(microtime(true) * 1_000.0);

                InsertValuesApplier::forValues(
                    [
                        'id' => $this->id(),
                        'platform' => $this->platform,
                        'email_address' => $posted['email'],
                        'name' => $posted['name'],
                        'subject' => $posted['subject'],
                        'message_type' => 'text',
                        'message_body' => $posted['message'],
                        'activity_last' => $msTimestamp,
                        'submitted_on' => $msTimestamp,
                        'submitted_ip' => isset($server['HTTP_X_REAL_IP']) && is_string($server['HTTP_X_REAL_IP'])
                            ? $server['HTTP_X_REAL_IP']
                            : null,
                        'submitted_user_agent' => isset($server['HTTP_USER_AGENT']) && is_string($server['HTTP_USER_AGENT']) && trim($server['HTTP_USER_AGENT']) !== ''
                            ? substr(trim($server['HTTP_USER_AGENT']), 0, 255)
                            : null,
                    ],
                )
                    ->apply($this->db->createQueryBuilder())
                    ->insert('platform_contact')
                    ->executeStatement();

                $state = 'submitted';
            }
        }

        return $this->view->render(
            $response,
            "/page/contact.twig",
            [
                'errors' => $errors,
                'posted' => $posted,
                'state' => $state,
            ],
        );
    }

    private function id(): string
    {
        return (string)(new Uuid7IdentifierGenerator())
            ->generate();
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     *
     * @psalm-pure
     *
     * @phpstan-assert array{email: string, name: string, subject: string, message: string} $data
     */
    private function validate(array $data): array
    {
        $errors = [];

        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            $errors['name'] = 'Naam is verplicht';
        } elseif (grapheme_strlen($data['name']) > 75) {
            $errors['name'] = 'Naam mag maximaal 75 tekens lang zijn';
        }

        if (!isset($data['email']) || !is_string($data['email']) || trim($data['email']) === '') {
            $errors['email'] = 'E-mail is verplicht';
        } elseif (filter_var($data['email'], FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false) {
            $errors['email'] = 'E-mail adres lijkt ongeldig';
        }

        if (!isset($data['subject']) || !is_string($data['subject']) || trim($data['subject']) === '') {
            $errors['subject'] = 'Onderwerp is verplicht';
        } elseif (grapheme_strlen($data['subject']) > 100) {
            $errors['subject'] = 'Onderwerp mag maximaal 100 tekens lang zijn';
        }

        if (!isset($data['message']) || !is_string($data['message']) || trim($data['message']) === '') {
            $errors['message'] = 'Bericht is verplicht';
        } elseif (grapheme_strlen($data['message']) > 5_000) {
            $errors['message'] = 'Bericht mag maximaal 5.000 tekens lang zijn';
        }

        return $errors;
    }
}
