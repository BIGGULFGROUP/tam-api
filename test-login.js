async function testLogin() {
    console.log("Logging in...");
    const loginRes = await fetch("http://127.0.0.1:8000/api/backend-admin/auth/login", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json"
        },
        body: JSON.stringify({
            email: "ahmaduabubakarr@gmail.com",
            password: "Admin@1234"
        })
    });
    
    const loginData = await loginRes.json();
    console.log("Login Response Status:", loginRes.status);
    console.log("Login Response Data:", loginData);
    
    if (!loginData.token) {
        console.error("No token received!");
        return;
    }
    
    console.log("\nCalling /auth/me with token...");
    const meRes = await fetch("http://127.0.0.1:8000/api/backend-admin/auth/me", {
        headers: {
            "Authorization": `Bearer ${loginData.token}`,
            "Accept": "application/json"
        }
    });
    
    console.log("Auth/Me Response Status:", meRes.status);
    const text = await meRes.text();
    try {
        console.log("Auth/Me Response Data:", JSON.parse(text));
    } catch (e) {
        console.log("Auth/Me Raw:", text);
    }
}

testLogin();
