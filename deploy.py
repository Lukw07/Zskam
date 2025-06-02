import requests
import argparse

# --- Configuration ---
# Your Coolify instance base URL and application details
BASE_API_URL = "http://82.29.179.61:8000/api/v1" # Base API URL
APPLICATION_UUID = "tocsk88kw08osswkcoswk0wc"    # This seems to be the application identifier based on your webhook

# Your API token.
# As discussed, for Laravel Sanctum tokens (like "1|s8eJvpkToFKs8iuBx6akKmJxN0xJVPFUc5wqsIswe497be72"),
# you typically use the part *after* the "1|" as the Bearer token.
# If Coolify expects the full string "1|...", adjust this variable.
API_TOKEN = "s8eJvpkToFKs8iuBx6akKmJxN0xJVPFUc5wqsIswe497be72"

def redeploy_app(force_redeploy=False):
    """
    Triggers the redeployment of the Coolify application using API token authentication.

    Args:
        force_redeploy (bool): Whether to force a rebuild (sets force=true).
    """
    # The endpoint for deploying via API might be slightly different than the webhook,
    # or it might be the same. We'll use the structure from your webhook.
    # If Coolify has a dedicated API endpoint like /applications/{uuid}/deploy, that might be cleaner.
    # For now, we adapt the webhook structure for an authenticated API call.
    deploy_url = f"{BASE_API_URL}/deploy" # This matches your webhook structure

    params = {
        'uuid': APPLICATION_UUID,
        'force': str(force_redeploy).lower() # API expects 'true' or 'false' as strings
    }

    headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': f'Bearer {API_TOKEN}' # API token is now always included
    }

    print(f"Attempting redeploy using API Token to URL: {deploy_url} with params: {params}")

    try:
        # Using POST request for deployment actions
        response = requests.post(deploy_url, headers=headers, params=params, timeout=30)

        print(f"\nRequest URL (as sent by requests): {response.url}") # Shows the actual URL called
        print(f"Status Code: {response.status_code}")

        if response.status_code >= 200 and response.status_code < 300:
            print("Redeploy triggered successfully!")
            try:
                print("Response JSON:", response.json())
            except requests.exceptions.JSONDecodeError:
                print("Response Content (if not JSON):", response.text)
        else:
            print("Failed to trigger redeploy.")
            print("Response Reason:", response.reason)
            try:
                print("Response JSON (error):", response.json())
            except requests.exceptions.JSONDecodeError:
                print("Response Content (error, if not JSON):", response.text)

    except requests.exceptions.RequestException as e:
        print(f"An error occurred: {e}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Redeploy a Coolify application using API token.")
    parser.add_argument(
        "--force",
        action="store_true",
        help="Force a rebuild of the application (sets force=true)."
    )
    args = parser.parse_args()

    redeploy_app(force_redeploy=args.force)