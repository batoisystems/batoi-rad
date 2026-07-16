# Batoi AIF Integration

## Boundary

Batoi AIF is the only AI execution layer in Batoi RAD. RAD controllers and
administrative screens may collect prompts, configuration, and display results,
but they must not implement provider HTTP clients, response parsing, model
routing, policy enforcement, or audit behavior.

The RAD adapter is under `rad/vendor/batoi/aif/src/Rad/`:

- `RadAifConfig` loads and resolves RAD AI profiles.
- `RadAifService` exposes RAD-oriented chat, completion, vision, and embedding
  operations backed by `AifGateway`.
- `RadCodeAssistService` handles RAD code suggestion prompts.
- `RadApiGatewayService` handles configured RAD AI API endpoints.
- `RadSuggestionClient` supports RAD Admin patch-generation flows.

## Configuration

RAD Admin writes local provider settings to `rad/config/ai-config.php`. This
file is ignored by Git and must not be committed. The default adapter is
OpenAI-compatible and supports Responses and Chat Completions endpoints.

Provider credentials remain opt-in. A configured provider is not invoked until
an application or administrator explicitly requests an AI operation.

## Additional Providers

Claude, Gemini, Azure OpenAI, and other providers must be added to Batoi AIF as
provider adapters implementing its contracts. Provider-specific clients must
not be added under `rad/core/`, `rad/admin/`, application microservices, or RAD
helper classes.
