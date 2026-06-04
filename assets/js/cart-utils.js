async function checkCart42() {
  try {
    const response = await fetch("/wp-json/wc/store/v1/cart");

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error("Cart notification API error:", error);
    return false;
  }
}

window.checkCart42 = checkCart42;
