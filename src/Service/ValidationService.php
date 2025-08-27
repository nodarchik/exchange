<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ApiConstants;
use App\Constants\CryptoPairs;
use App\Dto\RateQueryDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for handling validation logic
 * Centralizes validation concerns and error formatting
 */
class ValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Validate rate query DTO and return standardized errors
     */
    public function validateRateQuery(RateQueryDto $queryDto): ValidationResult
    {
        $violations = $this->validator->validate($queryDto);
        
        if (count($violations) === 0) {
            return ValidationResult::valid();
        }

        $errors = $this->formatViolations($violations);
        
        $this->logger->warning('API validation failed', ['errors' => $errors]);
        
        return ValidationResult::invalid($errors, ApiConstants::MESSAGE_INVALID_PARAMETERS);
    }

    /**
     * Validate date requirement for daily endpoints
     */
    public function validateDateRequirement(RateQueryDto $queryDto): ValidationResult
    {
        if ($queryDto->date === null) {
            $error = ['date' => ApiConstants::VALIDATION_REQUIRED_FIELD];
            
            $this->logger->warning('Date parameter missing for daily endpoint');
            
            return ValidationResult::invalid($error, ApiConstants::MESSAGE_DATE_REQUIRED);
        }

        return ValidationResult::valid();
    }

    /**
     * Validate if pair is supported
     */
    public function validateSupportedPair(string $pair): ValidationResult
    {
        if (!CryptoPairs::isSupported($pair)) {
            $error = ['pair' => ApiConstants::getUnsupportedPairMessage()];
            
            $this->logger->warning('Unsupported pair requested', ['pair' => $pair]);
            
            return ValidationResult::invalid($error, 'Unsupported trading pair');
        }

        return ValidationResult::valid();
    }

    /**
     * Format constraint violations to array
     */
    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        return $errors;
    }
}

/**
 * Value object representing validation result
 */
readonly class ValidationResult
{
    public function __construct(
        public bool $isValid,
        public array $errors = [],
        public string $message = ''
    ) {}

    public static function valid(): self
    {
        return new self(true);
    }

    public static function invalid(array $errors, string $message = ''): self
    {
        return new self(false, $errors, $message);
    }
}
