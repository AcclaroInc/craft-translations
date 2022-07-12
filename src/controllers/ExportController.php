<?php

namespace acclaro\translations\controllers;

use Craft;
use yii\web\Response;
use craft\web\Controller;
use craft\base\ElementInterface;
use acclaro\translations\Constants;
use yii\base\InvalidValueException;
use acclaro\translations\Translations;
use acclaro\translations\services\Exporter;
use craft\elements\db\ElementQueryInterface;

class ExportController extends Controller
{
    /**
     * @return \craft\web\Response
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionExportFiles()
    {
        $this->requirePostRequest();
        $request = Craft::$app->getRequest();

        $exporter = new Exporter($request->getRequiredParam('type', 'Raw'));
        $elementType = $request->getRequiredParam('elementType');
        $exporter->setElementType($elementType);

        if ($orderIds = $request->getBodyParam('orderIds')) {
            $exporter->setOrderIds(explode(',', $orderIds));
        }

        $filename = $exporter->getFilename();

        if ($exporter::isFormattable()) {
            $this->response->format = $this->request->getBodyParam('format', 'csv');
            $filename .= '.' . $this->response->format;
        }

        $this->response->setDownloadHeaders($filename);

        $export = $exporter->export($this->elementQuery($elementType));

        if ($exporter::isFormattable()) {
            if (!is_array($export)) {
                throw new InvalidValueException(get_class($exporter) . '::export() must return an array since isFormattable() returns true.');
            }

            $this->response->data = $export;

            switch ($this->response->format) {
                case Response::FORMAT_JSON:
                    $this->response->formatters[Response::FORMAT_JSON]['prettyPrint'] = true;
                    break;
                case Response::FORMAT_XML:
                    Craft::$app->language = 'en-US';
                    /** @var string|ElementInterface $elementType */
                    $this->response->formatters[Response::FORMAT_XML]['rootTag'] = $elementType::pluralLowerDisplayName();
                    break;
            }
        } else if (
            is_callable($export) ||
            is_resource($export) ||
            (is_array($export) && isset($export[0]) && is_resource($export[0]))
        ) {
            $this->response->stream = $export;
        } else {
            $this->response->data = $export;
            $this->response->format = Response::FORMAT_RAW;
        }

        return $this->response;
    }

	/**
	 * @return \craft\web\Response
	 * @throws \yii\web\BadRequestHttpException
	 */
	public function actionExportPreviewLinks()
	{
		$this->requirePostRequest();
		$request = Craft::$app->getRequest();

		// Get the id param
		$orderId = $request->getBodyParam('id');
		$files = json_decode($request->getBodyParam('files'), true);

		$order = Translations::$plugin->orderRepository->getOrderById($orderId);

		$fileName = $this->getFileName($order);
		$previewFile = fopen($fileName, "w");

		fputcsv($previewFile, ['OrderId', 'Title', 'SourceSite', 'TargetSite', 'Status', 'DateOrdered', 'PreviewUrl']);

		foreach ($order->getFiles() as $file) {
            if (in_array($file->id, $files)) {
                $row = [
                    $orderId,
                    $order->title,
                    $file->sourceSite,
                    $file->targetSite,
                    $file->status,
                    $order->dateOrdered,
                    $file->previewUrl ?? 'N/A'
                ];
                fputcsv($previewFile, $row);
            }
		}

		fclose($previewFile);

		return $this->asSuccess(null, ['previewFile' => $fileName]);
	}

    /**
     * Returns the element query based on the current params.
     *
     * @return ElementQueryInterface
     */
    protected function elementQuery($elementType): ElementQueryInterface
    {
        /** @var string|ElementInterface $elementType */
        $query = $elementType::find();

        // Override with the request's params
        if ($criteria = $this->request->getBodyParam('criteria')) {
            if (isset($criteria['trashed'])) {
                $criteria['trashed'] = filter_var($criteria['trashed'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
            if (isset($criteria['drafts'])) {
                $criteria['drafts'] = filter_var($criteria['drafts'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
            if (isset($criteria['draftOf'])) {
                if (is_numeric($criteria['draftOf']) && $criteria['draftOf'] != 0) {
                    $criteria['draftOf'] = (int)$criteria['draftOf'];
                } else {
                    $criteria['draftOf'] = filter_var($criteria['draftOf'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }
            }
            Craft::configure($query, $criteria);
        }

        return $query;
    }

	/**
	 * @param $order
	 * @return string
	 */
	private function getFileName($order)
	{
		$file_name =  sprintf('preview_links_%s', $order['id']);

		return Craft::$app->path->getTempPath() . '/' . $file_name . '.' . Constants::FILE_FORMAT_CSV;
	}
}
