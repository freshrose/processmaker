<?php
namespace ProcessMaker\Http\Controllers\Api;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use ProcessMaker\Facades\WorkflowManager;
use ProcessMaker\Http\Controllers\Controller;
use ProcessMaker\Http\Resources\ApiCollection;
use ProcessMaker\Http\Resources\Process as Resource;
use ProcessMaker\Http\Resources\ProcessRequests;
use ProcessMaker\Models\Process;

class ProcessController extends Controller
{
    use ResourceRequestsTrait;

    /**
     * @OA\Get(
     *     path="/processes",
     *     summary="Returns all processes that the user has access to",
     *     operationId="getProcesses",
     *     tags={"Process"},
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Filter results with a string. Searches Name, Description, and Status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="list of processes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Process")
     *         ),
     *     ),
     * )
     */
    public function index(Request $request)
    {
        $where = $this->getRequestFilterBy($request, ['name', 'description','status']);
        $orderBy = $this->getRequestSortBy($request, 'name');
        $perPage = $this->getPerPage($request);
        $include = $this->getRequestInclude($request);
        $processes = Process::with($include)
            ->where($where)
            ->orderBy(...$orderBy)
            ->paginate($perPage);
        return new ApiCollection($processes);
    }

    /**
     * Display the specified resource.
     *
     * @param $process
     *
     * @return Response
     * 
     * @OA\Get(
     *     path="/process/{processUuid}",
     *     summary="Get single process by ID",
     *     operationId="getProcessByUuid",
     *     tags={"Process"},
     *     @OA\Parameter(
     *         description="ID of pet to return",
     *         in="path",
     *         name="processUuid",
     *         required=true,
     *         @OA\Schema(
     *           type="string",
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully found the process",
     *         @OA\JsonContent(ref="#/components/schemas/Process")
     *     ),
     * )
     */
    public function show(Request $request, Process $process)
    {
        return new Resource($process);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        //Convert the string uuid to binary uuid
        $this->encodeRequestUuids($request, ['process_category_uuid']);
        $data = $request->json()->all();

        $process = new Process();
        $process->fill($data);

        //set current user
        $process->user_uuid = Auth::user()->uuid;

        if (isset($data['bpmn'])) {
            $process->bpmn = $data['bpmn'];
        }
        else {
            $process->bpmn = Process::getProcessTemplate('OnlyStartElement.bpmn');
        }
        //validate model trait
        $this->validateModel($process, Process::rules());
        $process->saveOrFail();
        return new Resource($process->refresh());
    }

    /**
     * Updates the current element
     *
     * @param Request $request
     * @param Process $process
     * @return ResponseFactory|Response
     * @throws \Throwable
     */
    public function update(Request $request, Process $process)
    {
        //Convert the string uuid to binary uuid
        $this->encodeRequestUuids($request, ['process_category_uuid']);
        $process->fill($request->json()->all());
        //validate model
        $this->validateModel($process, Process::rules($process));
        $process->saveOrFail();
        return new Resource($process->refresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Process $process
     *
     * @return ResponseFactory|Response
     * @throws \Illuminate\Validation\ValidationException
     */
    public function destroy(Process $process)
    {
        $this->validateModel($process, [
            'collaborations' => 'empty',
            'requests' => 'empty',
        ]);
        $process->delete();
        return response('', 204);
    }

    /**
     * Trigger an start event within a process.
     *
     * @param Process $process
     * @param Request $request
     *
     * @return \ProcessMaker\Http\Resources\ProcessRequests
     */
    public function triggerStartEvent(Process $process, Request $request)
    {
        //Get the event BPMN element
        $id = $request->input('event');
        if (!$id) {
            return abort(404);
        }
        $definitions = $process->getDefinitions();
        if (!$definitions->findElementById($id)) {
            return abort(404);
        }
        $event = $definitions->getEvent($id);
        $data = request()->input();
        //Trigger the start event
        $processRequest = WorkflowManager::triggerStartEvent($process, $event, $data);
        return new ProcessRequests($processRequest);
    }
    
    
}
