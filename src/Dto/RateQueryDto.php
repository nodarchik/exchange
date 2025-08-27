<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Data Transfer Object for rate query parameters
 * Ensures clean separation between HTTP layer and business logic
 */
final readonly class RateQueryDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Pair is required')]
        #[Assert\Choice(
            choices: ['EUR/BTC', 'EUR/ETH', 'EUR/LTC'],
            message: 'Invalid pair. Supported pairs are: EUR/BTC, EUR/ETH, EUR/LTC'
        )]
        public string $pair,

        #[Assert\Date(message: 'Date must be in YYYY-MM-DD format')]
        public ?string $date = null
    ) {}

    /**
     * Get parsed date as DateTimeImmutable
     * 
     * @throws \InvalidArgumentException If date format is invalid
     */
    public function getParsedDate(): ?\DateTimeImmutable
    {
        if ($this->date === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($this->date);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Invalid date format: {$this->date}", 0, $e);
        }
    }

    /**
     * Validate that the date is not in the future
     */
    #[Assert\Callback]
    public function validateDate(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->date === null) {
            return;
        }

        try {
            $parsedDate = $this->getParsedDate();
            $today = new \DateTimeImmutable('today');
            
            if ($parsedDate > $today) {
                $context->buildViolation('Date cannot be in the future')
                    ->atPath('date')
                    ->addViolation();
            }
        } catch (\InvalidArgumentException $e) {
            // Date format validation is handled by @Assert\Date
        }
    }
}
