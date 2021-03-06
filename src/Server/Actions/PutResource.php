<?php

/**
 * Author: Xooxx <xooxx.dev@gmail.com>
 * Date: 12/2/15
 * Time: 9:38 PM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Xooxx\JsonApi\Server\Actions;

use Xooxx\Laravel\Access\Facades\Gate;
use Exception;
use Xooxx\JsonApi\JsonApiSerializer;
use Xooxx\JsonApi\Server\Actions\Traits\ResponseTrait;
use Xooxx\JsonApi\Server\Data\DataException;
use Xooxx\JsonApi\Server\Data\DataObject;
use Xooxx\JsonApi\Server\Errors\Error;
use Xooxx\JsonApi\Server\Errors\ErrorBag;
use Xooxx\JsonApi\Server\Errors\NotFoundError;
use Xooxx\JsonApi\Server\Actions\Exceptions\ForbiddenException;
/**
 * Class PutResource.
 */
class PutResource
{
    use ResponseTrait;
    /**
     * @var \Xooxx\JsonApi\Server\Errors\ErrorBag
     */
    protected $errorBag;
    /**
     * @var JsonApiSerializer
     */
    protected $serializer;
    /**
     * @param JsonApiSerializer $serializer
     */
    public function __construct(JsonApiSerializer $serializer)
    {
        $this->serializer = $serializer;
        $this->errorBag = new ErrorBag();
    }
    /**
     * @param          $id
     * @param array    $data
     * @param          $className
     * @param callable $findOneCallable
     * @param callable $update
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function get($id, array $data, $className, callable $findOneCallable, callable $update)
    {
        try {
            DataObject::assertPut($data, $this->serializer, $className, $this->errorBag);
            $model = $findOneCallable();

            if (empty($model)) {
                $mapping = $this->serializer->getTransformer()->getMappingByClassName($className);
                return $this->resourceNotFound(new ErrorBag([new NotFoundError($mapping->getClassAlias(), $id)]));
            }
            Gate::authorize('update', $model);
            $values = DataObject::getAttributes($data, $this->serializer);
            $update($model, $data, $values, $this->errorBag);
            $response = $this->resourceUpdated($this->serializer->serialize($model));
        } catch (Exception $e) {
            $response = $this->getErrorResponse($e);
        }
        return $response;
    }
    /**
     * @param Exception $e
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function getErrorResponse(Exception $e)
    {
        switch (get_class($e)) {
            case ForbiddenException::class:
                $response = $this->forbidden($this->errorBag);
                break;
            case DataException::class:
                $response = $this->unprocessableEntity($this->errorBag);
                break;

            default:
                $response = $this->errorResponse(new ErrorBag([new Error('Bad Request', 'Request could not be served.')]));
        }
        return $response;
    }

    /**
     * @return ErrorBag
     */
    public function getErrorBag(){
        return $this->errorBag;
    }
}