import * as React from "react";
import * as SliderPrimitive from "@radix-ui/react-slider";

import { cn } from "@/lib/utils";

const Slider = React.forwardRef((props, ref) => {
  return (
    <SliderPrimitive.Root
      ref={ref}
      {...props}
      className={cn(
        "relative flex w-full touch-none select-none items-center",
        props.className
      )}
    >
      <SliderPrimitive.Track className={cn("relative h-1 w-full grow overflow-hidden rounded-full bg-slate-200")}>
        <SliderPrimitive.Range className={cn("relative h-full bg-slate-600")} />
      </SliderPrimitive.Track>
      <SliderPrimitive.Thumb className="relative h-4 w-4 rounded-full border-none bg-slate-600 ring-0 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-600 focus-visible:ring-offset-2 dark:ring-offset-1 dark:bg-white" />
    </SliderPrimitive.Root>
  );
});
Slider.displayName = SliderPrimitive.Root.displayName;

export { Slider };
