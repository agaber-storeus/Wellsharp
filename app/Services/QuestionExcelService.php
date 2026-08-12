<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Question;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionExcelService
{
    public const REQUIRED_HEADERS = ['question', 'type', 'difficulty', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'];

    public const HEADERS = [...self::REQUIRED_HEADERS, 'default_marks', 'solution'];

    private const MAX_QUESTION_LENGTH = 5000;

    private const MAX_OPTION_LENGTH = 1000;

    private const MAX_ANSWER_LENGTH = 1000;

    private const MAX_SOLUTION_LENGTH = 10000;

    public function template(): StreamedResponse
    {
        $this->ensureZipSupport();
        $spreadsheet = new Spreadsheet;
        $questions = $spreadsheet->getActiveSheet()->setTitle('Questions');
        $questions->fromArray([self::HEADERS, [
            'What is a well control barrier?', 'mcq', 'easy', 'A pressure-control element', 'A training room', '', '', 'A',
        ], [
            'A shut-in procedure should be followed when required.', 'true_false', 'medium', '', '', '', '', 'true',
        ], [
            'Enter the name of the primary barrier.', 'input', 'hard', '', '', '', '', 'Example answer',
        ]]);
        $this->styleQuestionSheet($questions);

        $instructions = $spreadsheet->createSheet()->setTitle('Instructions');
        $this->buildInstructionsSheet($instructions);
        $spreadsheet->setActiveSheetIndex(1);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'question-bank-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Course $course, UploadedFile $file): array
    {
        if (in_array(strtolower($file->getClientOriginalExtension()), ['xlsx', 'xls'], true)) {
            $this->ensureZipSupport();
        }
        $workbook = IOFactory::load($file->getRealPath());
        $sheet = $workbook->getSheetByName('Questions') ?: $workbook->getActiveSheet();
        $rawRows = $sheet->toArray('', true, true, false);
        $headers = array_map(function ($header): string {
            $header = (string) $header;
            $header = str_starts_with($header, "\xEF\xBB\xBF") ? substr($header, 3) : $header;
            $header = preg_replace('/^\x{FEFF}/u', '', $header) ?? $header;

            return Str::lower(trim($header));
        }, array_shift($rawRows) ?: []);
        $nonEmptyHeaders = array_values(array_filter($headers, fn (string $header): bool => $header !== ''));
        if (count($nonEmptyHeaders) !== count(array_unique($nonEmptyHeaders))) {
            throw new \InvalidArgumentException('The spreadsheet contains duplicate column headers.');
        }
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missing !== []) {
            throw new \InvalidArgumentException('Missing required columns: '.implode(', ', $missing));
        }

        $columnIndex = array_flip($headers);
        $existing = $course->questions()->pluck('question_text')->map(fn ($text): string => Question::normalizeText($text))->all();
        $seen = [];
        $rows = [];

        foreach ($rawRows as $index => $raw) {
            $rowNumber = $index + 2;
            $values = [];
            foreach (self::HEADERS as $header) {
                $values[$header] = isset($columnIndex[$header]) ? trim((string) ($raw[$columnIndex[$header]] ?? '')) : '';
            }
            if (count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            [$payload, $errors] = $this->normalizeRow($values);
            if ($errors === []) {
                $normalized = Question::normalizeText($payload['question_text']);
                if (in_array($normalized, $existing, true) || in_array($normalized, $seen, true)) {
                    $errors[] = 'Duplicate question text in this course or import file.';
                } else {
                    $seen[] = $normalized;
                }
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'question' => $values['question'],
                'type' => $values['type'],
                'difficulty' => $values['difficulty'],
                'status' => $errors === [] ? 'valid' : 'invalid',
                'errors' => $errors,
                'payload' => $errors === [] ? $payload : null,
            ];
        }

        return [
            'filename' => $file->getClientOriginalName(),
            'rows' => $rows,
            'total' => count($rows),
            'valid_count' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'valid')),
            'invalid_count' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'invalid')),
        ];
    }

    private function normalizeRow(array $values): array
    {
        $errors = [];
        $question = trim($values['question']);
        $type = Str::lower(str_replace(['-', ' '], '_', $values['type']));
        $difficulty = Str::lower($values['difficulty']);

        if ($question === '') {
            $errors[] = 'Question is required.';
        }
        if (! in_array($type, ['true_false', 'mcq', 'input'], true)) {
            $errors[] = 'Type must be true_false, mcq, or input.';
        }
        if (! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $errors[] = 'Difficulty must be easy, medium, or hard.';
        }

        $options = array_values(array_filter([$values['option_a'], $values['option_b'], $values['option_c'], $values['option_d']], fn (string $option): bool => $option !== ''));
        $answer = trim($values['correct_answer']);
        $payload = [
            'question_text' => $question,
            'type' => $type,
            'difficulty' => $difficulty,
            'default_marks' => $values['default_marks'] !== '' ? $values['default_marks'] : 1,
            'solution_text' => $values['solution'] ?: null,
        ];

        if (mb_strlen($question) > self::MAX_QUESTION_LENGTH) {
            $errors[] = 'Question must not exceed 5000 characters.';
        }
        if ($values['default_marks'] !== '' && (! is_numeric($values['default_marks']) || (float) $values['default_marks'] < 0 || (float) $values['default_marks'] > 999999.99)) {
            $errors[] = 'default_marks must be between 0 and 999999.99.';
        }
        if (mb_strlen($answer) > self::MAX_ANSWER_LENGTH) {
            $errors[] = 'correct_answer must not exceed 1000 characters.';
        }
        if (mb_strlen($values['solution']) > self::MAX_SOLUTION_LENGTH) {
            $errors[] = 'solution must not exceed 10000 characters.';
        }

        if ($type === 'mcq') {
            $optionColumns = [$values['option_a'], $values['option_b'], $values['option_c'], $values['option_d']];
            $optionGap = false;
            foreach ($optionColumns as $option) {
                if ($option === '') {
                    $optionGap = true;

                    continue;
                }
                if ($optionGap) {
                    $errors[] = 'MCQ option columns must be filled in order from option_a to option_d.';
                    break;
                }
            }
            foreach ($optionColumns as $option) {
                if (mb_strlen($option) > self::MAX_OPTION_LENGTH) {
                    $errors[] = 'Each MCQ option must not exceed 1000 characters.';
                    break;
                }
            }
        }
        if ($type === 'true_false') {
            $normalizedAnswer = Str::lower($answer);
            $map = ['true' => 'true', '1' => 'true', 'yes' => 'true', 'false' => 'false', '0' => 'false', 'no' => 'false'];
            if (! isset($map[$normalizedAnswer])) {
                $errors[] = 'True/False correct_answer must be true or false.';
            } else {
                $payload['correct_answer'] = $map[$normalizedAnswer];
            }
        } elseif ($type === 'input') {
            if ($answer === '') {
                $errors[] = 'Input questions require correct_answer.';
            } else {
                $payload['correct_answer'] = $answer;
            }
        } elseif ($type === 'mcq') {
            if (count($options) < 2) {
                $errors[] = 'MCQ rows require at least two options.';
            }
            $correctIndex = $this->correctOptionIndex($answer, $options);
            if ($correctIndex === null) {
                $errors[] = 'MCQ correct_answer must identify one supplied option.';
            } else {
                $payload['options'] = $options;
                $payload['correct_option'] = $correctIndex;
            }
        }

        return [$payload, $errors];
    }

    private function correctOptionIndex(string $answer, array $options): ?int
    {
        $normalized = Str::lower(trim($answer));
        $letterIndex = ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3];
        if (isset($letterIndex[$normalized]) && array_key_exists($letterIndex[$normalized], $options)) {
            return $letterIndex[$normalized];
        }
        if (ctype_digit($normalized)) {
            $index = (int) $normalized - 1;

            return array_key_exists($index, $options) ? $index : null;
        }
        foreach ($options as $index => $option) {
            if (Str::lower($option) === $normalized) {
                return $index;
            }
        }

        return null;
    }

    private function ensureZipSupport(): void
    {
        if (! class_exists('ZipArchive')) {
            throw new \RuntimeException('Excel XLS/XLSX support requires the PHP Zip extension (ext-zip).');
        }
    }

    private function styleQuestionSheet($questions): void
    {
        $questions->setShowGridlines(false);
        $questions->freezePane('A2');
        $questions->setAutoFilter('A1:J4');
        $questions->getRowDimension(1)->setRowHeight(32);
        $questions->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0E3554']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FFE97825']]],
        ]);
        $questions->getStyle('A2:J4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF4F8FA']],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFD8E1E7']]],
        ]);
        foreach (range('A', 'J') as $column) {
            $questions->getColumnDimension($column)->setWidth(match ($column) {
                'A' => 48,
                'B', 'C' => 18,
                'D', 'E', 'F', 'G' => 30,
                'H' => 26,
                'I' => 16,
                default => 42,
            });
        }
        foreach (range(2, 4) as $row) {
            $questions->getRowDimension($row)->setRowHeight(42);
        }
        $questions->getStyle('I2:I1000')->getNumberFormat()->setFormatCode('0.00');

        $headerNotes = [
            'A1' => "English: Question text shown to the trainee.\nالعربية: نص السؤال الذي سيظهر للمتدرب.",
            'B1' => "English: Question type.\nالعربية: نوع السؤال.",
            'C1' => "English: Difficulty level.\nالعربية: مستوى صعوبة السؤال.",
            'D1' => "English: First multiple-choice answer.\nالعربية: الاختيار الأول في سؤال الاختيار من متعدد.",
            'E1' => "English: Second multiple-choice answer.\nالعربية: الاختيار الثاني في سؤال الاختيار من متعدد.",
            'F1' => "English: Third multiple-choice answer.\nالعربية: الاختيار الثالث في سؤال الاختيار من متعدد.",
            'G1' => "English: Fourth multiple-choice answer.\nالعربية: الاختيار الرابع في سؤال الاختيار من متعدد.",
            'H1' => "English: Correct answer: True/False value, option letter, number, or exact option text.\nالعربية: الإجابة الصحيحة: قيمة صح/خطأ أو حرف الاختيار أو رقمه أو نص الاختيار بالكامل.",
            'I1' => "English: Points awarded when the answer is correct.\nالعربية: عدد الدرجات التي يحصل عليها المتدرب عند الإجابة الصحيحة.",
            'J1' => "English: Optional explanation or solution.\nالعربية: شرح أو حل اختياري للسؤال.",
        ];
        foreach ($headerNotes as $cell => $note) {
            $questions->getComment($cell)->getText()->createTextRun($note);
        }

        $typeValidation = $this->listValidation(
            'true_false,mcq,input',
            'Select a supported question type.',
            'اختر نوع سؤال مدعوم.'
        );
        $difficultyValidation = $this->listValidation(
            'easy,medium,hard',
            'Select easy, medium, or hard.',
            'اختر سهل أو متوسط أو صعب.'
        );
        for ($row = 2; $row <= 1000; $row++) {
            $questions->getCell("B{$row}")->setDataValidation(clone $typeValidation);
            $questions->getCell("C{$row}")->setDataValidation(clone $difficultyValidation);
        }
    }

    private function buildInstructionsSheet($instructions): void
    {
        $instructions->setShowGridlines(false);
        $instructions->setRightToLeft(false);
        $instructions->mergeCells('A1:F1');
        $instructions->setCellValue('A1', 'Question Bank Import Guide | دليل استيراد بنك الأسئلة');
        $instructions->mergeCells('A2:F2');
        $instructions->setCellValue('A2', 'Prepare, validate, and import reusable questions safely. | جهّز الأسئلة وراجعها واستوردها بأمان.');
        $instructions->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 18],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0E3554']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $instructions->getStyle('A2:F2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => 'FF456477'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEAF3F8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $instructions->getRowDimension(1)->setRowHeight(34);
        $instructions->getRowDimension(2)->setRowHeight(30);

        $guideRows = [
            ['Field / الحقل', 'English explanation', 'الشرح بالعربية', 'Required?', 'Allowed values / format', 'Example'],
            ['question', 'Question text shown to the trainee. Maximum 5,000 characters.', 'نص السؤال الذي سيظهر للمتدرب. الحد الأقصى 5,000 حرف.', 'Yes / نعم', 'Text', 'What is a well control barrier?'],
            ['type', 'Controls the answer interface used by the trainee.', 'يحدد واجهة الإجابة التي سيستخدمها المتدرب.', 'Yes / نعم', 'true_false | mcq | input', 'mcq'],
            ['difficulty', 'Difficulty classification used for question-bank filtering.', 'تصنيف الصعوبة المستخدم في تصفية بنك الأسئلة.', 'Yes / نعم', 'easy | medium | hard', 'medium'],
            ['option_a', 'First answer choice. Used only for MCQ questions.', 'الاختيار الأول. يستخدم فقط مع أسئلة الاختيار من متعدد.', 'For MCQ', 'Text, up to 1,000 characters', 'Primary barrier'],
            ['option_b', 'Second answer choice. MCQ requires at least two choices.', 'الاختيار الثاني. يجب أن يحتوي سؤال الاختيار من متعدد على خيارين على الأقل.', 'For MCQ', 'Text, up to 1,000 characters', 'Secondary barrier'],
            ['option_c', 'Third answer choice. Leave blank when not needed.', 'الاختيار الثالث. اتركه فارغاً إذا لم تكن هناك حاجة إليه.', 'Optional', 'Text, up to 1,000 characters', 'Training room'],
            ['option_d', 'Fourth answer choice. Leave blank when not needed.', 'الاختيار الرابع. اتركه فارغاً إذا لم تكن هناك حاجة إليه.', 'Optional', 'Text, up to 1,000 characters', 'Other'],
            ['correct_answer', 'For True/False use true, false, yes, no, 1, or 0. For MCQ use A-D, a 1-based number, or the exact option text. For Input provide the expected answer.', 'في أسئلة صح/خطأ استخدم true أو false أو yes أو no أو 1 أو 0. في الاختيار من متعدد استخدم A-D أو رقم الخيار أو نصه بالكامل. في سؤال الإدخال اكتب الإجابة المتوقعة.', 'Depends on type', 'Text; maximum 1,000 characters', 'A'],
            ['default_marks', 'Points awarded for a correct answer. Defaults to 1 when blank.', 'عدد الدرجات للإجابة الصحيحة. القيمة الافتراضية 1 إذا تُرك الحقل فارغاً.', 'Optional', 'Number from 0 to 999999.99', '1'],
            ['solution', 'Optional explanation or rationale stored with the question.', 'شرح أو سبب اختياري يتم حفظه مع السؤال.', 'Optional', 'Text, up to 10,000 characters', 'The primary barrier prevents flow.'],
        ];
        $instructions->fromArray($guideRows, null, 'A4');
        $guideEnd = 4 + count($guideRows) - 1;
        $instructions->getStyle('A4:F4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1C6B9B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $instructions->getStyle("A5:F{$guideEnd}")->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFD8E1E7']]],
        ]);
        for ($row = 5; $row <= $guideEnd; $row++) {
            $instructions->getRowDimension($row)->setRowHeight(58);
            if ($row % 2 === 1) {
                $instructions->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF4F8FA');
            }
        }
        $instructions->getStyle("C5:C{$guideEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $workflowStart = $guideEnd + 3;
        $instructions->mergeCells("A{$workflowStart}:F{$workflowStart}");
        $instructions->setCellValue("A{$workflowStart}", 'Import workflow and rules | خطوات وقواعد الاستيراد');
        $instructions->getStyle("A{$workflowStart}:F{$workflowStart}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 13],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE97825']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $workflowRows = [
            ['1', 'Open the Questions sheet and replace the example rows with your questions.', 'افتح ورقة Questions واستبدل صفوف الأمثلة بالأسئلة الخاصة بك.'],
            ['2', 'Do not rename the headers. Required columns must remain present.', 'لا تقم بتغيير أسماء الأعمدة. يجب أن تظل الأعمدة المطلوبة موجودة.'],
            ['3', 'For MCQ, fill choices from option_a in order without gaps and provide at least two choices.', 'في الاختيار من متعدد، املأ الخيارات بالترتيب من option_a دون فراغات، وأدخل خيارين على الأقل.'],
            ['4', 'Download, validate, preview, and confirm the file from the admin Question Import page.', 'نزّل الملف ثم ارفعه وراجعه وأكّد الاستيراد من صفحة استيراد الأسئلة في لوحة الإدارة.'],
            ['5', 'Duplicate normalized question text in the same Subject is rejected.', 'يتم رفض السؤال إذا كان نصه مكرراً بعد توحيد المسافات والحروف في نفس المادة.'],
            ['6', 'Optional images are added after import from the Question editor; embedded spreadsheet images are not imported.', 'تتم إضافة الصور الاختيارية بعد الاستيراد من محرر السؤال؛ لا يتم استيراد الصور المضمنة داخل ملف Excel.'],
            ['7', 'This template does not contain production data. Review every row before confirming.', 'هذا القالب لا يحتوي على بيانات إنتاجية. راجع كل صف قبل تأكيد الاستيراد.'],
        ];
        $instructions->fromArray($workflowRows, null, 'A'.($workflowStart + 1));
        $workflowEnd = $workflowStart + count($workflowRows);
        $instructions->getStyle('A'.($workflowStart + 1).":F{$workflowEnd}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        for ($row = $workflowStart + 1; $row <= $workflowEnd; $row++) {
            $instructions->getRowDimension($row)->setRowHeight(42);
            $instructions->mergeCells("C{$row}:F{$row}");
        }

        foreach (['A' => 24, 'B' => 54, 'C' => 64, 'D' => 16, 'E' => 34, 'F' => 30] as $column => $width) {
            $instructions->getColumnDimension($column)->setWidth($width);
        }
        $instructions->freezePane('A5');
    }

    private function listValidation(string $values, string $englishError, string $arabicError): DataValidation
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Invalid value | قيمة غير صحيحة')
            ->setError($englishError.' '.$arabicError)
            ->setPromptTitle('Choose from the list | اختر من القائمة')
            ->setPrompt($englishError.' '.$arabicError)
            ->setFormula1('"'.$values.'"');

        return $validation;
    }
}
