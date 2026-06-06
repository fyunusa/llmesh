# Autonomous Agents

LLMesh provides a multi-step agentic execution loop (`Agent`) that calls an LLM, executes requested tool calls, appends results back to the conversation history, and loops recursively until a final answer is generated.

---

## 1. Creating and Running an Agent

Construct an `Agent` by providing a provider, a system prompt, and a list of available tools.

```php
use LLMesh\Core\Agents\Agent;
use LLMesh\OpenAI\OpenAIProvider;

$agent = Agent::make(
    provider:     new OpenAIProvider($apiKey),
    systemPrompt: 'You are a helpful research assistant. Use search tools if needed.',
    tools:        [$weatherTool, $searchTool],
    maxSteps:     10 // Ceiling on LLM calls to prevent runaway loops
);

// Execute the agentic loop
$result = $agent->run('Tell me if I need an umbrella in Tokyo today.');

echo $result->finalText;
```

---

## 2. Agent Memory & Context

Agents are immutable. You can attach a conversation memory store so the agent remembers previous conversation turns across runs:

```php
use LLMesh\Core\Memory\RedisStore;

$store = new RedisStore($redisConnection);

$agentWithHistory = $agent->withMemory($store, sessionId: 'user-session-123');
$result = $agentWithHistory->run('Tell me about Tokyo weather.');
```

---

## 3. Monitoring & Step Callbacks

You can monitor intermediate step executions by registering a step callback using `onStep()`. This callback is executed after each loop iteration.

```php
$agent = $agent->onStep(function (AgentStep $step) {
    echo "Step Duration: " . $step->durationMs . "ms\n";
    if (!empty($step->toolCalls)) {
        foreach ($step->toolCalls as $call) {
            echo " -> Model requested tool: " . $call->name . "\n";
        }
    }
});
```

---

## 4. Understanding AgentResult

The `run()` method returns an `AgentResult` DTO which captures the complete execution trail:

```php
// The final text response
$text = $result->finalText;

// Total aggregated usage & cost across all loop turns
$usage = $result->getUsage();
echo "Total cost: $" . $usage->getEstimatedCost() . "\n";

// Access all intermediate step details (e.g. tool execution payloads)
foreach ($result->steps as $index => $step) {
    echo "Turn #{$index} generated " . count($step->toolCalls) . " tool calls.\n";
}
```
