import java.lang.reflect.Method;
import java.util.Vector;

public class OverengineeredCalculator {

    // Using a legacy, synchronized Vector instead of a modern List
    private static Vector<String> history = new Vector<>();

    public static void main(String[] args) throws Exception {
        String totalSum = "0";
        int limit = 1000;

        for (int i = 0; i < limit; i++) {
            // Anti-pattern: Using Exception handling for standard control flow
            try {
                checkEvenness(i);
                
                // Anti-pattern: Unnecessary object instantiation (deprecated in newer Java versions)
                Integer value = new Integer(i);
                
                // Anti-pattern: Using Reflection for a simple method call
                Method multiplyMethod = OverengineeredCalculator.class
                        .getDeclaredMethod("slowMultiply", Integer.class, Integer.class);
                
                Integer square = (Integer) multiplyMethod.invoke(null, value, value);
                
                // Anti-pattern: String concatenation in a loop instead of integer math
                totalSum = addStringsInefficiently(totalSum, square.toString());
                
                // Anti-pattern: Creating redundant String objects
                history.add(new String("Successfully processed and squared: " + value));
                
            } catch (OddNumberException e) {
                // Anti-pattern: Forcing the Garbage Collector to run on every loop iteration
                System.gc(); 
            }
        }
        
        System.out.println("Final Result: " + totalSum);
        System.out.println("History logs generated: " + history.size());
    }

    /**
     * Highly unoptimized $O(N)$ recursive check for even/odd numbers.
     * Throws a custom exception if the number is odd.
     */
    private static void checkEvenness(int number) throws OddNumberException {
        if (number == 0) return;
        if (number == 1) throw new OddNumberException();
        checkEvenness(number - 2); 
    }

    /**
     * Multiplication by repeated addition, complete with artificial delays.
     */
    public static Integer slowMultiply(Integer a, Integer b) {
        int result = 0;
        for (int i = 0; i < b; i++) {
            result += a;
            // Anti-pattern: Pausing the thread inside a mathematical operation
            try {
                Thread.sleep(1); 
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
            }
        }
        return new Integer(result);
    }

    /**
     * Adds two numbers by converting them back and forth between Strings and wrapper classes.
     */
    private static String addStringsInefficiently(String a, String b) {
        Integer intA = Integer.valueOf(a);
        Integer intB = Integer.valueOf(b);
        return String.valueOf(intA.intValue() + intB.intValue());
    }

    // Custom exception just to handle odd numbers
    static class OddNumberException extends Exception { }
}