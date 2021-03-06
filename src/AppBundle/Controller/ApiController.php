<?php

namespace AppBundle\Controller;

use AppBundle\Exceptions\ {
        NoQuestionsException, 
        NoQuizzesException,
        TooFewAnswersException
    };
use AppBundle\Linters\JsonRpcLinter;
use AppBundle\Utilities\Responder;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\ {Controller};
use Symfony\Component\HttpFoundation\ {JsonResponse, Request};

class ApiController extends Controller
{
    /**
     * @Route("/api", name="api")
     * @Method({"POST"})
     */
    public function apiAction(Request $request)
    {
        $content = $request->getContent();

        $jsonDecoded = json_decode($content, true);

        if (null === $jsonDecoded) {
            $response = Responder::errorResponse(
                null,
                -32700,
                'Parse error'
            );
            return new JsonResponse($response);
        }

        $id = $jsonDecoded['id'];

        if (!JsonRpcLinter::getResult($jsonDecoded)->getValid()) {
            $response = Responder::errorResponse(
                $id,
                -32600,
                'Invalid Request'
            );
            return new JsonResponse($response);
        }

        $method = $jsonDecoded['method'];

        switch($method) {
            case 'newQuestion':
                if (!array_key_exists('params', $jsonDecoded)) {
                    return $this->invalidParams($id, 'Missing params');
                }

                if (!array_key_exists('quizId', $jsonDecoded['params'])) {
                    return $this->invalidParams($id, 'Missing quiz');
                }

                $quizId = $jsonDecoded['params']['quizId'];
                return $this->newQuestion($id, $quizId);

            case 'answerQuestion':
                if (!array_key_exists('params', $jsonDecoded)) {
                    return $this->invalidParams($id, 'Missing params');
                }

                if (!array_key_exists('questionId', $jsonDecoded['params'])) {
                    return $this->invalidParams($id, 'Missing question');
                }

                if (!array_key_exists('guessId', $jsonDecoded['params'])) {
                    return $this->invalidParams($id, 'Missing answer');
                }

                $guessId = $jsonDecoded['params']['guessId'];
                $questionId = $jsonDecoded['params']['questionId'];
                return $this->answerQuestion($guessId, $id, $questionId);

            case 'getQuizzes':
                return $this->getQuizzes($id);

            default:
                $response = Responder::errorResponseData(
                    $id,
                    -32601,
                    'Method not found',
                    $method . ' not found'
                );
                return new JsonResponse($response);
        }
    }

    private function newQuestion($id, $quizId = null)
    {
        $entityManager = $this->getDoctrine()->getManager();

        try {

            $question = $entityManager->getRepository('AppBundle:Question')->getRandomQuestion($quizId);
            
            $rightAnswer = $question->getAnswer();

            $possibleAnswers = $entityManager->getRepository('AppBundle:Answer')->getPossibleAnswers($question);

        } catch (NoQuestionsException $noQuestionsException) {

            $response = Responder::errorResponse(
                $id,
                1,
                'No Questions Exception'
            );
            return new JsonResponse($response);

        } catch (TooFewAnswersException $tooFewAnswersException) {

            $response = Responder::errorResponse(
                $id,
                2,
                'Too Few Answers Exception'
            );
            return new JsonResponse($response);
        }

        $possibleAnswersJson = array_map(function ($answer) {
            return [
                'id' => $answer->getId(),
                'text' => $answer->getText(),
            ];
        }, $possibleAnswers);

        $response = [
            'id' => $id,
            'jsonrpc' => '2.0',
            'result' => [
                  'question' => [
                      'id' => $question->getId(),
                      'text' => $question->getText(),
                  ],
                  'answers' => $possibleAnswersJson,
              ],
        ];

        return new JsonResponse($response);
    }

    private function answerQuestion($guessId, $id, $questionId)
    {
        if (!is_int($guessId)) {
            return $this->invalidParams($id);
        }

        $entityManager = $this->getDoctrine()->getManager();

        $correctId = $entityManager->getRepository('AppBundle:Question')->findOneById($questionId)->getAnswer()->getId();

        $response = [
            'id' => $id,
            'jsonrpc' => '2.0',
            'result' => [
                'correctId' => $correctId,
            ],
        ];
        
        return new JsonResponse($response);
    }

    private function invalidParams($id, $data)
    {
        $response = Responder::errorResponseData(
            $id,
            -32602,
            'Invalid params',
            $data
        );
        return new JsonResponse($response);
    }

    private function getQuizzes($id)
    {

        $entityManager = $this->getDoctrine()->getManager();

        try {
            $quizzes = $entityManager->getRepository('AppBundle:Quiz')->getQuizzes();
        } catch (NoQuizzesException $noQuizzesException) {
            $response = Responder::errorResponse(
                $id,
                3,
                'No Quizzes Exception'
            );
            return new JsonResponse($response);
        }

        $quizzes = array_map(function ($quiz) {
            return [
                'id' => $quiz->getId(),
                'text' => $quiz->getText(),
            ];
        }, $quizzes);

        $response = [
            'id' => $id,
            'jsonrpc' => '2.0',
            'result' => [
                'quizzes' => $quizzes,
            ],
        ];

        return new JsonResponse($response); 
    }
}
